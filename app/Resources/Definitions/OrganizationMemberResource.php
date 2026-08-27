<?php

declare(strict_types=1);

namespace App\Resources\Definitions;

use App\Data\OrganizationMemberData;
use App\Enums\FilterType;
use App\Enums\MembershipStatus;
use App\Models\Organization;
use App\Models\OrganizationMembership;
use App\Policies\OrganizationMembershipPolicy;
use App\Resources\ResourceContract;
use App\Resources\ScopedToOrganization;
use App\Support\ResourceFilter;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

final class OrganizationMemberResource implements ResourceContract
{
    use ScopedToOrganization;

    public function key(): string
    {
        return 'organization-members';
    }

    public function label(): string
    {
        return 'Organization members';
    }

    public function model(): string
    {
        return OrganizationMembership::class;
    }

    public function dataClass(): string
    {
        return OrganizationMemberData::class;
    }

    public function policy(): string
    {
        return OrganizationMembershipPolicy::class;
    }

    public function url(Model $record): string
    {
        return route('organization-member.edit');
    }

    /**
     * A membership row carries no name of its own; people search for the person.
     *
     * @return list<string>
     */
    public function searchable(): array
    {
        return ['user.name', 'user.email'];
    }

    /**
     * A membership row carries no name of its own; it orders by the person, through the same relation search matches on.
     *
     * @return list<string>
     */
    public function sortable(): array
    {
        return ['user.name', 'user.email', 'status', 'created_at'];
    }

    /**
     * A members list is read to answer two questions: who is suspended, and who
     * arrived when.
     *
     * @return list<ResourceFilter>
     */
    public function filters(): array
    {
        return [
            new ResourceFilter(
                key: 'status',
                label: __('Status'),
                type: FilterType::Select,
                column: 'status',
                options: array_column(MembershipStatus::cases(), 'value'),
            ),
            new ResourceFilter(
                key: 'active',
                label: __('Account active'),
                type: FilterType::Boolean,
                column: 'user.is_active',
            ),
            new ResourceFilter(
                key: 'joined',
                label: __('Joined'),
                type: FilterType::DateRange,
                column: 'joined_at',
            ),
        ];
    }

    public function recordLabel(Model $record): string
    {
        assert($record instanceof OrganizationMembership);

        return $record->user->name;
    }

    public function recordDescription(Model $record): string
    {
        assert($record instanceof OrganizationMembership);

        return $record->user->email;
    }

    /**
     * Memberships carry no global scope, because a person lists their own
     * memberships across organizations. Search reaches them through the bound
     * organization's relation instead.
     *
     * @return Builder<OrganizationMembership>
     */
    public function scopedQuery(): Builder
    {
        return $this->scopedToOrganization(
            fn (Organization $organization): Builder => $organization->memberships()->getQuery(),
        );
    }
}
