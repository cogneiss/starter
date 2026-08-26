<?php

declare(strict_types=1);

namespace App\Data;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * One hit in the command palette. Everything the palette renders is decided
 * here, so the frontend never branches on the kind of record it is drawing.
 */
#[TypeScript('SearchResult')]
final class SearchResultData extends Data
{
    public function __construct(
        public string $label,
        public ?string $description,
        public string $url,
    ) {}
}
