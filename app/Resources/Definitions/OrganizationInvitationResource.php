<?php

declare(strict_types=1);

namespace App\Resources\Definitions;

use App\Data\OrganizationInvitationData;
use App\Enums\FilterType;
use App\Models\Organization;
use App\Models\OrganizationInvitation;
use App\Models\Role;
use App\Policies\OrganizationInvitationPolicy;
use App\Resources\ResourceColumn;
use App\Resources\ResourceContract;
use App\Support\OrganizationContext;
use App\Support\ResourceFilter;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

final class OrganizationInvitationResource implements ResourceContract
{
    /**
     * @var list<string>|null
     */
    private ?array $roles = null;

    public function key(): string
    {
        return 'organization-invitations';
    }

    public function label(): string
    {
        return 'Organization invitations';
    }

    public function model(): string
    {
        return OrganizationInvitation::class;
    }

    public function dataClass(): string
    {
        return OrganizationInvitationData::class;
    }

    public function policy(): string
    {
        return OrganizationInvitationPolicy::class;
    }

    /**
     * Pending invitations are listed on the members screen; the token link is
     * the invited person's entry point, not an in-app destination.
     */
    public function url(Model $record): string
    {
        return route('organization-member.edit');
    }

    /**
     * @return list<string>
     */
    public function searchable(): array
    {
        return ['email', 'role'];
    }

    /**
     * Alphabetical by address: an invitation list is read to find one address, not to see which arrived first.
     *
     * @return list<string>
     */
    public function sortable(): array
    {
        return ['email', 'role', 'expires_at', 'created_at'];
    }

    /**
     * Which roles were offered, and which invitations are about to lapse.
     *
     * @return list<ResourceFilter>
     */
    public function filters(): array
    {
        return [
            new ResourceFilter(
                key: 'role',
                label: __('Role'),
                type: FilterType::MultiSelect,
                column: 'role',
                options: $this->roles(),
            ),
            new ResourceFilter(
                key: 'expires',
                label: __('Expires'),
                type: FilterType::DateRange,
                column: 'expires_at',
            ),
        ];
    }

    /**
     * @return list<ResourceColumn>
     */
    public function columns(): array
    {
        return [
            new ResourceColumn(key: 'email', label: __('Email')),
            new ResourceColumn(key: 'role', label: __('Role')),
            new ResourceColumn(key: 'expires_at', label: __('Expires')),
        ];
    }

    public function recordLabel(Model $record): string
    {
        assert($record instanceof OrganizationInvitation);

        return $record->email;
    }

    public function recordDescription(Model $record): string
    {
        assert($record instanceof OrganizationInvitation);

        return $record->role;
    }

    /**
     * @return Builder<OrganizationInvitation>
     */
    public function scopedQuery(): Builder
    {
        return OrganizationInvitation::query();
    }

    /**
     * The roles this organization actually has, read once per request: the
     * facet counts ask for the filters several times over, and a role list does
     * not change between two of those asks.
     *
     * @return list<string>
     */
    private function roles(): array
    {
        $organization = resolve(OrganizationContext::class)->get();

        if (! $organization instanceof Organization) {
            return [];
        }

        if ($this->roles !== null) {
            return $this->roles;
        }

        $names = [];

        foreach (Role::query()
            ->where('organization_id', $organization->id)
            ->orderBy('name')
            ->get() as $role) {
            $names[] = $role->name;
        }

        return $this->roles ??= $names;
    }
}
