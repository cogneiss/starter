<?php

declare(strict_types=1);

namespace App\Resources\Definitions;

use App\Data\OrganizationData;
use App\Models\Organization;
use App\Policies\OrganizationPolicy;
use App\Resources\ResourceContract;
use App\Resources\ScopedToOrganization;
use App\Support\ResourceFilter;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

final class OrganizationResource implements ResourceContract
{
    use ScopedToOrganization;

    public function key(): string
    {
        return 'organizations';
    }

    public function label(): string
    {
        return 'Organizations';
    }

    public function model(): string
    {
        return Organization::class;
    }

    public function dataClass(): string
    {
        return OrganizationData::class;
    }

    public function policy(): string
    {
        return OrganizationPolicy::class;
    }

    /**
     * The organization you are looking at is always the one bound to the
     * request, so its settings screen takes no parameter.
     */
    public function url(Model $record): string
    {
        return route('organization.edit');
    }

    /**
     * @return list<string>
     */
    public function searchable(): array
    {
        return ['name', 'slug'];
    }

    /**
     * One organization is ever in reach, so the order is a formality.
     *
     * @return list<string>
     */
    public function sortable(): array
    {
        return ['name', 'slug', 'created_at'];
    }

    /**
     * @return list<ResourceFilter>
     */
    public function filters(): array
    {
        return [];
    }

    public function recordLabel(Model $record): string
    {
        assert($record instanceof Organization);

        return $record->name;
    }

    public function recordDescription(Model $record): string
    {
        assert($record instanceof Organization);

        return $record->slug;
    }

    /**
     * The only organization in reach is the one bound to the request. Anything
     * else is another tenant.
     *
     * @return Builder<Organization>
     */
    public function scopedQuery(): Builder
    {
        return $this->scopedToOrganization(
            fn (Organization $organization): Builder => Organization::query()->whereKey($organization->id),
        );
    }
}
