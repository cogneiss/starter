<?php

declare(strict_types=1);

namespace App\Ai\Blocks;

use App\Enums\AiBlockType;
use InvalidArgumentException;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript('AiTableBlock')]
final class TableBlock extends Data implements AiBlock
{
    public AiBlockType $type = AiBlockType::Table;

    /**
     * @param  list<string>  $columns
     * @param  list<list<string>>  $rows
     */
    public function __construct(
        public array $columns,
        public array $rows,
    ) {
        foreach ($this->rows as $row) {
            if (count($row) !== count($this->columns)) {
                throw new InvalidArgumentException('Every row must have one cell per column.');
            }
        }
    }
}
