<?php

declare(strict_types=1);

namespace App\Data;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * One choice inside a filter, with how many rows it would leave.
 *
 * The count is what makes a facet a facet rather than a dropdown: it is read off
 * the list's own query, so an option showing zero is an option worth greying out
 * instead of a dead end someone has to click to discover.
 */
#[TypeScript('ResourceFilterOption')]
final class ResourceFilterOptionData extends Data
{
    public function __construct(
        public string $value,
        public string $label,
        public int $count,
    ) {}
}
