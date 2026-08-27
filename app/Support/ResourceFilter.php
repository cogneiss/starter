<?php

declare(strict_types=1);

namespace App\Support;

use App\Enums\FilterType;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One filter a resource offers, and everything the app does with it.
 *
 * A filter declares its column and its type; the type decides how a value is
 * read out of the query string, how it narrows the query and how it is written
 * back into a URL. Nothing about a filter is decided by the page, so two screens
 * listing the same resource cannot disagree about what `f[status]=active` means.
 *
 * A value arriving from the query string is normalised before it is used, and
 * normalising is allowed to fail: a shape that makes no sense for the type
 * returns null and the filter is simply not applied. A hostile query string
 * therefore produces an unfiltered list rather than a 500.
 *
 * @phpstan-type NormalizedFilterValue string|bool|array<int|string, int|float|string|null>
 * @phpstan-type SerializedFilterValue string|array<int|string, string>
 */
final readonly class ResourceFilter
{
    /**
     * @param  string  $column  Column on the resource's table, or 'relation.column' through a belongsTo.
     * @param  list<string>  $options  The allowed values for a select or multi-select. Anything else is discarded.
     */
    public function __construct(
        public string $key,
        public string $label,
        public FilterType $type,
        public string $column,
        public array $options = [],
    ) {}

    /**
     * The value as the rest of the app may use it, or null when the query string
     * said something this filter cannot mean.
     *
     * @return NormalizedFilterValue|null
     */
    public function normalize(mixed $value): string|bool|array|null
    {
        return match ($this->type) {
            FilterType::Select => $this->normalizeOption($value),
            FilterType::MultiSelect => $this->normalizeOptions($value),
            FilterType::Boolean => $this->normalizeBoolean($value),
            FilterType::Range => $this->normalizeRange($value),
            FilterType::DateRange => $this->normalizeDateRange($value),
        };
    }

    /**
     * The normalised value as query-string parameters, so a filtered view can be
     * copied out of the address bar and opened again unchanged.
     *
     * @param  NormalizedFilterValue  $value
     * @return SerializedFilterValue
     */
    public function serialize(string|bool|array $value): string|array
    {
        if (is_bool($value)) {
            return $value ? '1' : '0';
        }

        if (is_string($value)) {
            return $value;
        }

        $edges = [];

        foreach ($value as $bound => $edge) {
            if ($edge !== null) {
                $edges[$bound] = (string) $edge;
            }
        }

        // A multi-select is a list of chosen options, so it is reindexed; a
        // range keeps its bound names, and a bound left unset is left out.
        return $this->type === FilterType::MultiSelect ? array_values($edges) : $edges;
    }

    /**
     * @param  Builder<covariant Model>  $query
     * @param  NormalizedFilterValue  $value
     */
    public function apply(Builder $query, string|bool|array $value): void
    {
        if (! str_contains($this->column, '.')) {
            $this->constrain($query, $this->column, $value);

            return;
        }

        [$name, $field] = explode('.', $this->column, 2);

        $query->whereHas($name, function (Builder $related) use ($field, $value): void {
            $this->constrain($related, $field, $value);
        });
    }

    /**
     * How many rows each option would leave, in one grouped query.
     *
     * The query handed in is the list's own query with every *other* filter
     * already applied and this filter's own constraint left off — the standard
     * faceted-search rule, so unticking an option shows what ticking it would
     * cost rather than what it already excluded.
     *
     * @param  Builder<covariant Model>  $query
     * @return array<string, int>
     */
    public function counts(Builder $query): array
    {
        if (! $this->type->countsOptions()) {
            return [];
        }

        $counts = [];

        foreach ($query->reorder()->toBase()->select($this->groupable($query))
            ->selectRaw('count(*) as aggregate')
            ->groupBy('facet')
            ->get() as $row) {
            $facet = $row->facet;

            if (! is_scalar($facet)) {
                continue;
            }

            $counts[$this->label($facet)] = is_numeric($row->aggregate) ? (int) $row->aggregate : 0;
        }

        return $counts;
    }

    /**
     * @param  Builder<covariant Model>  $query
     * @param  NormalizedFilterValue  $value
     */
    private function constrain(Builder $query, string $column, string|bool|array $value): void
    {
        if (! is_array($value)) {
            $query->where($column, $value);

            return;
        }

        if (array_is_list($value)) {
            $query->whereIn($column, $value);

            return;
        }

        [$low, $high] = $this->type === FilterType::DateRange ? ['from', 'to'] : ['min', 'max'];

        $this->constrainBetween($query, $column, $value, $low, $high);
    }

    /**
     * @param  Builder<covariant Model>  $query
     * @param  array<int|string, int|float|string|null>  $value
     */
    private function constrainBetween(Builder $query, string $column, array $value, string $low, string $high): void
    {
        foreach ([$low => '>=', $high => '<='] as $bound => $operator) {
            $edge = $value[$bound] ?? null;

            if ($edge === null) {
                continue;
            }

            if ($this->type === FilterType::DateRange) {
                $query->whereDate($column, $operator, (string) $edge);
            } else {
                $query->where($column, $operator, $edge);
            }
        }
    }

    /**
     * The column to group counts by, as a select the database can group on. A
     * filter reaching through a relation groups by a correlated subquery for the
     * same reason ordering does: one record stays one row.
     *
     * @param  Builder<covariant Model>  $query
     * @return list<string>|array<string, Builder<covariant Model>>
     */
    private function groupable(Builder $query): array
    {
        if (! str_contains($this->column, '.')) {
            return [$query->getModel()->qualifyColumn($this->column).' as facet'];
        }

        [$name, $field] = explode('.', $this->column, 2);

        $relation = $query->getModel()->{$name}();
        assert($relation instanceof BelongsTo);

        return ['facet' => $relation->getRelated()->newQuery()
            ->select($field)
            ->whereColumn(
                $relation->getQualifiedOwnerKeyName(),
                $relation->getQualifiedForeignKeyName(),
            )];
    }

    /**
     * Counts are keyed by the same strings the query string uses, so a boolean
     * column coming back as `true`, `1` or `'t'` still lands on one key.
     */
    private function label(string|int|float|bool $facet): string
    {
        if ($this->type !== FilterType::Boolean) {
            return (string) $facet;
        }

        return in_array($facet, [true, 1, '1', 't', 'true'], true) ? '1' : '0';
    }

    private function normalizeOption(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        return in_array($value, $this->options, true) ? $value : null;
    }

    /**
     * @return list<string>|null
     */
    private function normalizeOptions(mixed $value): ?array
    {
        if (! is_array($value)) {
            return null;
        }

        $chosen = [];

        foreach ($value as $option) {
            if (is_string($option) && in_array($option, $this->options, true)) {
                $chosen[] = $option;
            }
        }

        // Sorted and deduplicated, so two URLs that tick the same boxes in a
        // different order are the same URL.
        $chosen = array_values(array_unique($chosen));
        sort($chosen);

        return $chosen === [] ? null : $chosen;
    }

    private function normalizeBoolean(mixed $value): ?bool
    {
        return match ($value) {
            '1', 'true', true => true,
            '0', 'false', false => false,
            default => null,
        };
    }

    /**
     * @return array{min: int|float|null, max: int|float|null}|null
     */
    private function normalizeRange(mixed $value): ?array
    {
        if (! is_array($value)) {
            return null;
        }

        $min = is_numeric($value['min'] ?? null) ? $value['min'] + 0 : null;
        $max = is_numeric($value['max'] ?? null) ? $value['max'] + 0 : null;

        if ($min === null && $max === null) {
            return null;
        }

        // A backwards range is a typo or a probe, not a request for no rows.
        if ($min !== null && $max !== null && $min > $max) {
            return null;
        }

        return ['min' => $min, 'max' => $max];
    }

    /**
     * @return array{from: string|null, to: string|null}|null
     */
    private function normalizeDateRange(mixed $value): ?array
    {
        if (! is_array($value)) {
            return null;
        }

        $from = $this->date($value['from'] ?? null);
        $to = $this->date($value['to'] ?? null);

        if ($from === null && $to === null) {
            return null;
        }

        if ($from !== null && $to !== null && $from > $to) {
            return null;
        }

        return ['from' => $from, 'to' => $to];
    }

    /**
     * A plain calendar date, or nothing. Anything the database would have had to
     * interpret — a relative string, a timestamp, an almost-date — is nothing.
     */
    private function date(mixed $value): ?string
    {
        // Checked before it is parsed: Carbon raises on a string it cannot read
        // at all, and a query string is allowed to say anything.
        if (! is_string($value) || preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) !== 1) {
            return null;
        }

        $date = CarbonImmutable::createFromFormat('!Y-m-d', $value);

        // Carbon rolls an impossible date over rather than refusing it, so the
        // round trip is the check: only a date that prints back unchanged is one.
        return $date instanceof CarbonImmutable && $date->format('Y-m-d') === $value ? $value : null;
    }
}
