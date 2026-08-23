<?php

declare(strict_types=1);

namespace App\Actions;

use App\Enums\MembershipStatus;
use App\Models\OrganizationMembership;

final readonly class ReactivateOrganizationMembership
{
    public function handle(OrganizationMembership $membership): OrganizationMembership
    {
        $membership->forceFill([
            'status' => MembershipStatus::Active,
            'joined_at' => $membership->joined_at ?? now(),
        ])->save();

        return $membership;
    }
}
