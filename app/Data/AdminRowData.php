<?php

declare(strict_types=1);

namespace App\Data;

use App\Resources\ResourceColumn;
use Illuminate\Database\Eloquent\Model;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * One row on an admin page: the record's id and its cells, already rendered to
 * text by the same column definitions the CSV export uses. One shape for every
 * admin resource means one React page for all of them.
 */
#[TypeScript('AdminRow')]
final class AdminRowData extends Data
{
    /**
     * @param  array<string, string>  $cells
     */
    public function __construct(
        public string $id,
        public array $cells,
    ) {}

    /**
     * @param  list<ResourceColumn>  $columns
     */
    public static function fromModel(Model $record, array $columns): self
    {
        $cells = [];

        foreach ($columns as $column) {
            $cells[$column->key] = $column->valueFor($record);
        }

        $key = $record->getKey();
        assert(is_string($key) || is_int($key));

        return new self(id: (string) $key, cells: $cells);
    }
}
