<?php

declare(strict_types=1);

namespace App\Resources\Definitions;

use App\Data\ActivityData;
use App\Enums\FilterType;
use App\Models\Activity;
use App\Models\Organization;
use App\Policies\ActivityPolicy;
use App\Resources\ResourceColumn;
use App\Resources\ResourceContract;
use App\Support\OrganizationContext;
use App\Support\ResourceFilter;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

final class AuditLogResource implements ResourceContract
{
    public function key(): string
    {
        return 'audit-log';
    }

    public function label(): string
    {
        return 'Audit log';
    }

    public function model(): string
    {
        return Activity::class;
    }

    public function dataClass(): string
    {
        return ActivityData::class;
    }

    public function policy(): string
    {
        return ActivityPolicy::class;
    }

    /**
     * An audit entry is a line in a ledger, not a destination of its own; the
     * list is where it is read.
     */
    public function url(Model $record): string
    {
        return route('audit-log.index');
    }

    /**
     * @return list<string>
     */
    public function searchable(): array
    {
        return ['description'];
    }

    /**
     * Newest first: an audit log is read backwards from an incident.
     *
     * @return list<string>
     */
    public function sortable(): array
    {
        return ['created_at', 'event', 'description'];
    }

    /**
     * @return list<ResourceFilter>
     */
    public function filters(): array
    {
        return [
            new ResourceFilter(
                key: 'causer',
                label: __('Member'),
                type: FilterType::MultiSelect,
                column: 'causer_id',
                options: $this->causers(),
            ),
            new ResourceFilter(
                key: 'event',
                label: __('Event'),
                type: FilterType::MultiSelect,
                column: 'event',
                options: ['created', 'updated', 'deleted', 'role_changed', 'exported'],
            ),
            new ResourceFilter(
                key: 'subject',
                label: __('Record type'),
                type: FilterType::MultiSelect,
                column: 'subject_type',
                options: array_values(array_filter([...config()->array('audit.models'), ...config()->array('audit.extra')], is_string(...))),
            ),
            new ResourceFilter(
                key: 'when',
                label: __('When'),
                type: FilterType::DateRange,
                column: 'created_at',
            ),
        ];
    }

    /**
     * @return list<ResourceColumn>
     */
    public function columns(): array
    {
        return [
            new ResourceColumn(key: 'description', label: __('Description')),
            new ResourceColumn(key: 'event', label: __('Event')),
            new ResourceColumn(key: 'subject_type', label: __('Record type')),
            new ResourceColumn(key: 'causer.name', label: __('Member')),
            new ResourceColumn(key: 'created_at', label: __('When')),
        ];
    }

    public function recordLabel(Model $record): string
    {
        assert($record instanceof Activity);

        return $record->description;
    }

    public function recordDescription(Model $record): string
    {
        assert($record instanceof Activity);

        return (string) $record->created_at;
    }

    /**
     * @return Builder<Activity>
     */
    public function scopedQuery(): Builder
    {
        return Activity::query();
    }

    /**
     * The user ids that have caused an entry in this organization. Ids rather
     * than names because a filter option must equal the stored column value.
     *
     * @return list<string>
     */
    private function causers(): array
    {
        $organization = resolve(OrganizationContext::class)->get();

        if (! $organization instanceof Organization) {
            return [];
        }

        /** @var list<string> */
        return Activity::query()
            ->whereNotNull('causer_id')
            ->distinct()
            ->orderBy('causer_id')
            ->pluck('causer_id')
            ->all();
    }
}
