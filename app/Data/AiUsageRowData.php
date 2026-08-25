<?php

declare(strict_types=1);

namespace App\Data;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * One line of a usage breakdown: an agent, or a model tier, and what it cost.
 */
#[TypeScript('AiUsageRow')]
final class AiUsageRowData extends Data
{
    public function __construct(
        public string $name,
        public int $runs,
        public int $tokens,
        public int $cost_micros,
    ) {}
}
