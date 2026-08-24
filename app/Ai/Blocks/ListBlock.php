<?php

declare(strict_types=1);

namespace App\Ai\Blocks;

use App\Enums\AiBlockType;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript('AiListBlock')]
final class ListBlock extends Data implements AiBlock
{
    public AiBlockType $type = AiBlockType::ListItems;

    /**
     * @param  list<string>  $items
     */
    public function __construct(
        public array $items,
        public bool $ordered = false,
    ) {}
}
