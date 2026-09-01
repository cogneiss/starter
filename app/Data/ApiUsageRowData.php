<?php

declare(strict_types=1);

namespace App\Data;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * One line of an API usage breakdown: a day, an endpoint or a token, and how
 * many requests it answered for.
 */
#[TypeScript('ApiUsageRow')]
final class ApiUsageRowData extends Data
{
    public function __construct(
        public string $name,
        public int $requests,
    ) {}
}
