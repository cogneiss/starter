<?php

declare(strict_types=1);

namespace App\Resources\Definitions;

use App\Data\OrganizationInvitationData;
use App\Models\OrganizationInvitation;
use App\Policies\OrganizationInvitationPolicy;
use App\Resources\ResourceContract;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

final class OrganizationInvitationResource implements ResourceContract
{
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
}
