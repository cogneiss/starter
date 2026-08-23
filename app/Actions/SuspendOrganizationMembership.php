<?php

declare(strict_types=1);

namespace App\Actions;

use App\Enums\MembershipStatus;
use App\Models\OrganizationMembership;

final readonly class SuspendOrganizationMembership
{
    public function __construct(private AssertNotLastActiveOwner $owners) {}

    /**
     * Suspension keeps the row, so history and audit survive.
     */
    public function handle(OrganizationMembership $membership): OrganizationMembership
    {
        $this->owners->handle($membership);

        $membership->forceFill(['status' => MembershipStatus::Suspended])->save();

        return $membership;
    }
}
