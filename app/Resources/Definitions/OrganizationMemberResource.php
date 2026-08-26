<?php

declare(strict_types=1);

namespace App\Resources\Definitions;

use App\Data\OrganizationMemberData;
use App\Models\Organization;
use App\Models\OrganizationMembership;
use App\Policies\OrganizationMembershipPolicy;
use App\Resources\ResourceContract;
use App\Resources\ScopedToOrganization;
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
