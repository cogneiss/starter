<?php

declare(strict_types=1);

namespace App\Data;

use Spatie\LaravelData\Attributes\DataCollectionOf;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * What the AI layer has cost since a given moment, read from the audit log.
 *
 * The same payload answers `php artisan ai:usage --json` and the organization's
 * usage page, so a figure quoted in a terminal and a figure on a screen cannot
 * disagree.
 */
#[TypeScript('AiUsage')]
final class AiUsageData extends Data
{
    /**
     * @param  list<AiUsageRowData>  $agents
     * @param  list<AiUsageRowData>  $tiers
     */
    public function __construct(
        public string $since,
        public int $runs,
        public int $tokens,
        public int $cost_micros,
        public int $blocked,
        #[DataCollectionOf(AiUsageRowData::class)]
        public array $agents,
        #[DataCollectionOf(AiUsageRowData::class)]
        public array $tiers,
    ) {}
}
