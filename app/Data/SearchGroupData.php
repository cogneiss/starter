<?php

declare(strict_types=1);

namespace App\Data;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * The hits for one resource, with the heading the palette shows above them.
 */
#[TypeScript('SearchGroup')]
final class SearchGroupData extends Data
{
    /**
     * @param  list<SearchResultData>  $results
     */
    public function __construct(
        public string $key,
        public string $label,
        public array $results,
    ) {}
}
