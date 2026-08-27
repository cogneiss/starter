<?php

declare(strict_types=1);

namespace Tests\Fixtures\Resources\Filters;

use App\Enums\AiAuditStatus;
use App\Models\AiAuditLog;
use App\Models\Organization;
use App\Models\User;
use Carbon\CarbonImmutable;

/**
 * Two records that differ on every filterable column, so one filter of any type
 * separates them and no test has to invent its own data.
 */
final class AuditLogFixture
{
    /**
     * @return array{matching: AiAuditLog, other: AiAuditLog}
     */
    public static function seed(Organization $organization): array
    {
        return [
            'matching' => self::matching($organization),
            'other' => self::other($organization),
        ];
    }

    public static function matching(Organization $organization): AiAuditLog
    {
        return AiAuditLog::factory()->create([
            'organization_id' => $organization->id,
            'user_id' => User::factory()->create(['is_active' => true]),
            'status' => AiAuditStatus::Ok,
            'tier' => 'cheap',
            'total_tokens' => 100,
            'created_at' => CarbonImmutable::parse('2026-01-10 09:00:00'),
        ]);
    }

    public static function other(Organization $organization): AiAuditLog
    {
        return AiAuditLog::factory()->create([
            'organization_id' => $organization->id,
            'user_id' => User::factory()->create(['is_active' => false]),
            'status' => AiAuditStatus::Blocked,
            'tier' => 'smart',
            'total_tokens' => 500,
            'created_at' => CarbonImmutable::parse('2026-02-10 09:00:00'),
        ]);
    }

    /**
     * The query string that keeps only {@see self::matching()}, one entry per
     * filter type.
     *
     * @return array<string, array<string, mixed>>
     */
    public static function narrowing(): array
    {
        return [
            'select' => ['status' => 'ok'],
            'multi-select' => ['tier' => ['cheap']],
            'boolean' => ['active' => '1'],
            'range' => ['tokens' => ['min' => '50', 'max' => '200']],
            'date-range' => ['used' => ['from' => '2026-01-01', 'to' => '2026-01-31']],
        ];
    }
}
