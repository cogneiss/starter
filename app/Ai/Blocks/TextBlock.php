<?php

declare(strict_types=1);

namespace App\Ai\Blocks;

use App\Enums\AiBlockType;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript('AiTextBlock')]
final class TextBlock extends Data implements AiBlock
{
    public AiBlockType $type = AiBlockType::Text;

    public function __construct(
        public string $text,
    ) {}
}
