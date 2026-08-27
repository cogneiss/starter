<?php

declare(strict_types=1);

namespace App\Resources;

use BackedEnum;
use DateTimeInterface;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Gate;

/**
 * One column of a resource, as an export writes it.
 *
 * A column may name the ability a person needs to see it. That check belongs
 * here rather than on the screen: a column hidden from someone in the table but
 * present in their CSV would be a column that is not really hidden at all.
 */
final readonly class ResourceColumn
{
    /**
     * @param  string  $key  Attribute on the record, or 'relation.attribute' through a belongsTo.
     * @param  string|null  $ability  Ability the acting person needs, or null when everyone listing may see it.
     */
    public function __construct(
        public string $key,
        public string $label,
        public ?string $ability = null,
    ) {}

    /**
     * The columns this person may see, in the order the resource declared them.
     *
     * @param  list<self>  $columns
     * @return list<self>
     */
    public static function visibleTo(array $columns, ?Authenticatable $user): array
    {
        $visible = [];

        foreach ($columns as $column) {
            if ($column->ability === null || Gate::forUser($user)->allows($column->ability)) {
                $visible[] = $column;
            }
        }

        return $visible;
    }

    /**
     * The record's value for this column as one CSV field.
     *
     * A spreadsheet has no types, so everything arrives as text: an enum as the
     * value it is stored as, a date in a format that sorts, and anything a cell
     * cannot hold — a missing relation, a cast object — as an empty field.
     */
    public function valueFor(Model $record): string
    {
        $value = data_get($record, $this->key);

        if ($value instanceof BackedEnum) {
            $value = $value->value;
        }

        if ($value instanceof DateTimeInterface) {
            $value = $value->format('Y-m-d H:i:s');
        }

        return is_scalar($value) ? (string) $value : '';
    }
}
