<?php

declare(strict_types=1);

namespace App\Data;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * One listed record, spelled out for the drawer that sits over the list.
 *
 * The drawer renders this and nothing else, so whatever scope found the record
 * is the only way it can reach the screen.
 */
#[TypeScript('RecordPeek')]
final class RecordPeekData extends Data
{
    /**
     * @param  array<string, string>  $fields  Label to value, in the order they are shown.
     */
    public function __construct(
        public string $id,
        public string $title,
        public array $fields,
    ) {}
}
