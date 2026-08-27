<?php

declare(strict_types=1);

namespace Tests\Fixtures\Resources\Filters;

use App\Enums\AiAuditStatus;
use App\Enums\FilterType;
use App\Models\AiAuditLog;
use App\Resources\ResourceContract;
use App\Support\ResourceFilter;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * A resource declaring one filter of every type, over the one shipped model
 * that has a numeric column a range can mean anything on.
 *
 * It lives in the test suite rather than in `app/Resources/Definitions` because
 * the audit log is not a screen: nothing in the application lists it, and a
 * resource the app does not use would be a fixture pretending to be a feature.
 * The five types themselves are production code — this only declares them.
 */
final class AuditLogResource implements ResourceContract
{
    public function key(): string
    {
        return 'audit-logs';
    }

    public function label(): string
    {
        return 'Audit logs';
    }

    public function model(): string
    {
        return AiAuditLog::class;
    }

    public function dataClass(): string
    {
        return AuditLogRowData::class;
    }

    public function policy(): ?string
    {
        return null;
    }

    public function url(Model $record): string
    {
        return route('dashboard');
    }

    /**
     * @return list<string>
     */
    public function searchable(): array
    {
        return ['agent'];
    }

    /**
     * @return list<string>
     */
    public function sortable(): array
    {
        return ['created_at', 'total_tokens'];
    }

    /**
     * One filter of every type the app supports, including one reaching through
     * a belongsTo relation.
     *
     * @return list<ResourceFilter>
     */
    public function filters(): array
    {
        return [
            new ResourceFilter(
                key: 'status',
                label: 'Status',
                type: FilterType::Select,
                column: 'status',
                options: array_column(AiAuditStatus::cases(), 'value'),
            ),
            new ResourceFilter(
                key: 'tier',
                label: 'Tier',
                type: FilterType::MultiSelect,
                column: 'tier',
                options: ['cheap', 'smart'],
            ),
            new ResourceFilter(
                key: 'active',
                label: 'Active user',
                type: FilterType::Boolean,
                column: 'user.is_active',
            ),
            new ResourceFilter(
                key: 'tokens',
                label: 'Tokens',
                type: FilterType::Range,
                column: 'total_tokens',
            ),
            new ResourceFilter(
                key: 'used',
                label: 'Used',
                type: FilterType::DateRange,
                column: 'created_at',
            ),
        ];
    }

    public function recordLabel(Model $record): string
    {
        assert($record instanceof AiAuditLog);

        return $record->agent;
    }

    public function recordDescription(Model $record): ?string
    {
        return null;
    }

    /**
     * @return Builder<AiAuditLog>
     */
    public function scopedQuery(): Builder
    {
        return AiAuditLog::query();
    }
}
