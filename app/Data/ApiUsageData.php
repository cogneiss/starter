<?php

declare(strict_types=1);

namespace App\Data;

use Spatie\LaravelData\Attributes\DataCollectionOf;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * What the read API has served an organization since a given moment, read from
 * the append-only request log, plus where the organization stands against its
 * current rate tier.
 */
#[TypeScript('ApiUsage')]
final class ApiUsageData extends Data
{
    /**
     * @param  list<ApiUsageRowData>  $daily
     * @param  list<ApiUsageRowData>  $endpoints
     * @param  list<ApiUsageRowData>  $tokens
     */
    public function __construct(
        public string $since,
        public int $requests,
        public int $throttled,
        public string $tier,
        public int $limit,
        public int $remaining,
        #[DataCollectionOf(ApiUsageRowData::class)]
        public array $daily,
        #[DataCollectionOf(ApiUsageRowData::class)]
        public array $endpoints,
        #[DataCollectionOf(ApiUsageRowData::class)]
        public array $tokens,
    ) {}
}
