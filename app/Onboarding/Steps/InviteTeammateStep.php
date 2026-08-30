<?php

declare(strict_types=1);

namespace App\Onboarding\Steps;

use App\Models\Organization;
use App\Models\OrganizationInvitation;
use App\Models\User;
use App\Onboarding\StepContract;

/**
 * Get a second person in. An organization of one is a personal account.
 *
 * Either half counts: an invitation that has gone out, or a teammate who is
 * already a member because they arrived some other way.
 */
final class InviteTeammateStep implements StepContract
{
    public function key(): string
    {
        return 'invite';
    }

    public function title(): string
    {
        return 'Invite a teammate';
    }

    public function description(): string
    {
        return 'Send one invitation so somebody else can see what you build here.';
    }

    public function route(): string
    {
        return 'organization-invitation.create';
    }

    public function isRequired(): bool
    {
        return true;
    }

    public function order(): int
    {
        return 20;
    }

    public function isComplete(User $user, Organization $organization): bool
    {
        return OrganizationInvitation::query()->exists()
            || $organization->memberships()->count() > 1;
    }
}
