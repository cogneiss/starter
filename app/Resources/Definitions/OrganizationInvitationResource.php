<?php

declare(strict_types=1);

namespace App\Resources\Definitions;

use App\Data\OrganizationInvitationData;
use App\Models\OrganizationInvitation;
use App\Policies\OrganizationInvitationPolicy;
use App\Resources\ResourceContract;
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
}
