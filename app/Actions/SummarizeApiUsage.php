<?php

declare(strict_types=1);

namespace App\Actions;

use App\Data\ApiUsageData;
use App\Data\ApiUsageRowData;
use App\Enums\KnownFeatures;
use App\Models\ApiRequestLog;
use App\Models\Organization;
use Carbon\CarbonInterface;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\RateLimiter;
use Laravel\Pennant\Feature;

/**
 * What the read API has served an organization, read from the append-only
 * request log, and where the organization stands right now against its tier's
 * per-organization window.
 */
final readonly class SummarizeApiUsage
{
    public function handle(Organization $organization, CarbonInterface $since): ApiUsageData
    {
        $totals = $this->query($organization, $since)
            ->selectRaw('count(*) as requests')
            ->selectRaw('coalesce(sum(case when status = 429 then 1 else 0 end), 0) as throttled')
            ->first();

        [$tier, $limit] = $this->tier($organization);

        return new ApiUsageData(
            since: $since->toIso8601String(),
            requests: $this->toInt($totals?->requests),
            throttled: $this->toInt($totals?->throttled),
            tier: $tier,
            limit: $limit,
            remaining: max(0, RateLimiter::remaining('api:org:'.$organization->id, $limit)),
            daily: $this->breakdown($organization, $since, "to_char(created_at, 'YYYY-MM-DD') as name", byName: true),
            endpoints: $this->breakdown($organization, $since, 'path as name'),
            tokens: $this->tokens($organization, $since),
        );
    }

    /**
     * The organization's tier name and its per-organization requests-per-minute
     * cap, falling back to the documented default tier for a name config does
     * not know.
     *
     * @return array{string, int}
     */
    private function tier(Organization $organization): array
    {
        $tier = Feature::for($organization)->value(KnownFeatures::API_RATE_TIER);
        $tiers = config()->array('api.rate_tiers.tiers');

        if (! is_string($tier) || ! array_key_exists($tier, $tiers)) {
            $tier = config()->string('api.rate_tiers.default');
        }

        $limit = is_array($tiers[$tier]) ? $tiers[$tier]['per_organization'] : 0;

        return [$tier, is_int($limit) ? $limit : 0];
    }

    /**
     * Request counts per distinct value of the expression, busiest first.
     *
     * @param  literal-string  $name  The expression the count is reported under.
     * @return list<ApiUsageRowData>
     */
    private function breakdown(Organization $organization, CarbonInterface $since, string $name, bool $byName = false): array
    {
        $rows = $this->query($organization, $since)
            ->selectRaw($name)
            ->selectRaw('count(*) as requests')
            ->groupBy('name')
            ->when($byName, fn (Builder $query): Builder => $query->orderBy('name'))
            ->unless($byName, fn (Builder $query): Builder => $query->orderByDesc('requests')->orderBy('name'))
            ->limit(50)
            ->get()
            ->map(fn (object $row): ApiUsageRowData => new ApiUsageRowData(
                name: is_string($row->name) ? $row->name : 'unknown',
                requests: $this->toInt($row->requests),
            ))
            ->all();

        return array_values($rows);
    }

    /**
     * The busiest tokens by name. A pruned token's rows survive it; they are
     * reported under a tombstone rather than dropped.
     *
     * @return list<ApiUsageRowData>
     */
    private function tokens(Organization $organization, CarbonInterface $since): array
    {
        $rows = $this->query($organization, $since)
            ->leftJoin('personal_access_tokens', 'personal_access_tokens.id', '=', 'api_request_logs.api_token_id')
            ->selectRaw("coalesce(personal_access_tokens.name, 'deleted token') as name")
            ->selectRaw('count(*) as requests')
            ->groupBy('name')
            ->orderByDesc('requests')
            ->orderBy('name')
            ->limit(10)
            ->get()
            ->map(fn (object $row): ApiUsageRowData => new ApiUsageRowData(
                name: is_string($row->name) ? $row->name : 'unknown',
                requests: $this->toInt($row->requests),
            ))
            ->all();

        return array_values($rows);
    }

    /**
     * The page is the only caller and always names an organization, so unlike
     * the AI summary this query never spans tenants.
     */
    private function query(Organization $organization, CarbonInterface $since): Builder
    {
        return ApiRequestLog::withoutOrganizationScope()
            ->toBase()
            ->where('api_request_logs.organization_id', $organization->id)
            ->where('api_request_logs.created_at', '>=', $since);
    }

    /**
     * Aggregates come back as whatever the driver felt like: an int on Postgres,
     * a string on some others, null from an empty table.
     */
    private function toInt(mixed $value): int
    {
        return is_numeric($value) ? (int) $value : 0;
    }
}
