<?php

declare(strict_types=1);

namespace App\Ai\Blocks;

use App\Enums\AiBlockType;
use Illuminate\Support\Str;
use Spatie\LaravelData\Attributes\Computed;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * The one block that renders markup, so the markup is never the model's. The
 * html is derived in the constructor from the markdown the model sent. It is a
 * computed property, so a payload carrying its own `html` does not overwrite
 * the sanitizer's output — it produces no block at all.
 */
#[TypeScript('AiMarkdownBlock')]
final class MarkdownBlock extends Data implements AiBlock
{
    public AiBlockType $type = AiBlockType::Markdown;

    #[Computed]
    public string $html;

    public function __construct(
        public string $markdown,
    ) {
        $this->html = Str::markdown($this->markdown, [
            'html_input' => 'strip',
            'allow_unsafe_links' => false,
        ]);
    }
}
