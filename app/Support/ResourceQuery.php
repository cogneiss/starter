<?php

declare(strict_types=1);

namespace App\Support;

use App\Data\ResourceFilterData;
use App\Resources\ResourceContract;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Http\Request;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\LiteralTypeScriptType;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * What a list request asked for, after the request has been disbelieved.
 *
 * The query string is public: anyone can put anything in it. So nothing here is
 * taken as given. The sort column is checked against the resource's own
 * allowlist, the page size against a fixed set, the page against arithmetic. A
 * hostile query string produces a boring list, never an error page and never a
 * column the screen was not meant to expose.
 *
 * @phpstan-import-type NormalizedFilterValue from ResourceFilter
 * @phpstan-import-type SerializedFilterValue from ResourceFilter
 */
#[TypeScript('ResourceQuery')]
final class ResourceQuery extends Data
{
    /**
     * The page sizes a list offers. Anything else is rounded down to one of
     * these, so a `per=100000` cannot ask the database for the whole table.
     *
     * @var list<int>
     */
    public const array PER_OPTIONS = [10, 25, 50, 100];

    /**
     * Long enough for a name or an address, short enough that the LIKE stays
     * cheap. Matches the search endpoint's own limit.
     */
    private const int MAX_TERM = 255;

    /**
     * @param  'asc'|'desc'  $dir
     * @param  array<string, NormalizedFilterValue>  $filters  Normalised values, keyed by filter key.
     */
    public function __construct(
        public string $q,
        public ?string $sort,
        #[LiteralTypeScriptType("'asc' | 'desc'")]
        public string $dir,
        public int $page,
        public int $per,
        #[LiteralTypeScriptType('Record<string, string | boolean | Record<string, string | number> | Array<string>>')]
        public array $filters = [],
    ) {}

    public static function fromRequest(Request $request, ResourceContract $resource): self
    {
        return new self(
            q: self::term($request->query('q')),
            sort: self::sort($request->query('sort'), $resource),
            dir: $request->query('dir') === 'desc' ? 'desc' : 'asc',
            page: self::page($request->query('page')),
            per: self::per($request->query('per')),
            filters: self::filters($request->query('f'), $resource),
        );
    }

    /**
     * The search endpoint wants the matching half of this and none of the
     * ordering, so it says so rather than faking a request.
     */
    public static function forTerm(string $term, int $per): self
    {
        return new self(q: self::term($term), sort: null, dir: 'asc', page: 1, per: $per);
    }

    /**
     * The columns a term reaches through, so the caller can eager load them and
     * a list does not fire a query per row.
     *
     * @param  list<string>  $columns
     * @return list<string>
     */
    public static function relationsIn(array $columns): array
    {
        $relations = [];

        foreach ($columns as $column) {
            if (str_contains($column, '.')) {
                $relations[] = explode('.', $column, 2)[0];
            }
        }

        return array_values(array_unique($relations));
    }

    /**
     * The query as URL parameters: everything a second person needs to see this
     * exact list, and nothing the server would have assumed anyway.
     *
     * This is the half of the round trip `fromRequest()` cannot check on its
     * own. A filtered, sorted, paged view is copied out of the address bar and
     * opened again unchanged, because both directions go through here.
     *
     * @return array<string, string|int|array<string, SerializedFilterValue>>
     */
    public function toQueryParameters(ResourceContract $resource): array
    {
        $parameters = [];

        if ($this->q !== '') {
            $parameters['q'] = $this->q;
        }

        if ($this->sort !== null) {
            $parameters['sort'] = $this->sort;
            $parameters['dir'] = $this->dir;
        }

        if ($this->page > 1) {
            $parameters['page'] = $this->page;
        }

        if ($this->per !== self::PER_OPTIONS[0]) {
            $parameters['per'] = $this->per;
        }

        $filters = [];

        foreach ($resource->filters() as $filter) {
            $value = $this->filters[$filter->key] ?? null;

            if ($value !== null) {
                $filters[$filter->key] = $filter->serialize($value);
            }
        }

        if ($filters !== []) {
            $parameters['f'] = $filters;
        }

        return $parameters;
    }

    /**
     * The filters as the table draws them, each counted against the list minus
     * its own constraint.
     *
     * @param  Builder<covariant Model>  $query  The list's query before any filter is applied.
     * @return list<ResourceFilterData>
     */
    public function facets(Builder $query, ResourceContract $resource): array
    {
        $facets = [];

        foreach ($resource->filters() as $filter) {
            $counted = clone $query;

            // The search term narrows every count: a facet describes the list
            // in front of the person, not the table behind it.
            $this->applyTerm($counted, $resource->searchable());

            // Every other filter narrows the count; this one does not. Ticking
            // an option must show what it would leave, not what it already left.
            foreach ($resource->filters() as $other) {
                $value = $this->filters[$other->key] ?? null;

                if ($value !== null && $other->key !== $filter->key) {
                    $other->apply($counted, $value);
                }
            }

            $facets[] = ResourceFilterData::fromFilter(
                $filter,
                $filter->counts($counted),
                $this->filters[$filter->key] ?? null,
            );
        }

        return $facets;
    }

    /**
     * @param  Builder<covariant Model>  $query
     * @return Builder<covariant Model>
     */
    public function applyTo(Builder $query, ResourceContract $resource): Builder
    {
        $this->applyTerm($query, $resource->searchable());
        $this->applyFilters($query, $resource);
        $this->applyOrder($query);

        return $query;
    }

    /**
     * Only the filters the resource declares, only in shapes their type can
     * mean. An unknown key, a range where a list belongs, a date that is not a
     * date: all discarded here, so nothing downstream has to be suspicious.
     *
     * @return array<string, NormalizedFilterValue>
     */
    private static function filters(mixed $value, ResourceContract $resource): array
    {
        if (! is_array($value)) {
            return [];
        }

        $filters = [];

        foreach ($resource->filters() as $filter) {
            $normalized = $filter->normalize($value[$filter->key] ?? null);

            if ($normalized !== null) {
                $filters[$filter->key] = $normalized;
            }
        }

        return $filters;
    }

    private static function term(mixed $value): string
    {
        return is_string($value) ? mb_substr(mb_trim($value), 0, self::MAX_TERM) : '';
    }

    /**
     * An unknown column is not an error, it is a stale link: someone kept a
     * bookmark across a rename. The list still renders, in the resource's own
     * default order.
     */
    private static function sort(mixed $value, ResourceContract $resource): ?string
    {
        $sortable = $resource->sortable();

        if (is_string($value) && in_array($value, $sortable, true)) {
            return $value;
        }

        return $sortable[0] ?? null;
    }

    private static function page(mixed $value): int
    {
        return is_numeric($value) ? max(1, (int) $value) : 1;
    }

    /**
     * Rounded down to an allowlisted size, so a bigger number asks for more
     * rows only up to the largest page the app offers.
     */
    private static function per(mixed $value): int
    {
        $asked = is_numeric($value) ? (int) $value : 0;

        $allowed = self::PER_OPTIONS;

        for ($index = count($allowed) - 1; $index > 0; $index--) {
            if ($asked >= $allowed[$index]) {
                return $allowed[$index];
            }
        }

        return $allowed[0];
    }

    /**
     * @param  Builder<covariant Model>  $query
     */
    private function applyFilters(Builder $query, ResourceContract $resource): void
    {
        foreach ($resource->filters() as $filter) {
            $value = $this->filters[$filter->key] ?? null;

            if ($value !== null) {
                $filter->apply($query, $value);
            }
        }
    }

    /**
     * @param  Builder<covariant Model>  $query
     * @param  list<string>  $columns
     */
    private function applyTerm(Builder $query, array $columns): void
    {
        if ($this->q === '' || $columns === []) {
            return;
        }

        // The wildcards are ours; the ones the person typed are literal.
        $like = '%'.addcslashes($this->q, '%_\\').'%';

        $query->where(function (Builder $inner) use ($columns, $like): void {
            foreach ($columns as $column) {
                // Case-insensitive: a person typing "ada" means Ada, and on
                // Postgres a plain LIKE would not agree.
                if (! str_contains($column, '.')) {
                    $inner->orWhereLike($column, $like, caseSensitive: false);

                    continue;
                }

                [$name, $field] = explode('.', $column, 2);

                $inner->orWhereHas($name, function (Builder $related) use ($field, $like): void {
                    $related->whereLike($field, $like, caseSensitive: false);
                });
            }
        });
    }

    /**
     * @param  Builder<covariant Model>  $query
     */
    private function applyOrder(Builder $query): void
    {
        if ($this->sort === null) {
            return;
        }

        if (! str_contains($this->sort, '.')) {
            $query->orderBy($this->sort, $this->dir);
        } else {
            [$name, $field] = explode('.', $this->sort, 2);

            $relation = $query->getModel()->{$name}();
            assert($relation instanceof BelongsTo);

            // Ordering through a relation is a correlated subquery rather than a
            // join, so one record stays one row however the relation grows.
            $query->orderBy(
                $relation->getRelated()->newQuery()
                    ->select($field)
                    ->whereColumn(
                        $relation->getQualifiedOwnerKeyName(),
                        $relation->getQualifiedForeignKeyName(),
                    ),
                $this->dir,
            );
        }

        // A page boundary in the middle of a run of equal values would otherwise
        // repeat or drop rows, because the database is free to reorder ties.
        $query->orderBy($query->getModel()->getQualifiedKeyName());
    }
}
