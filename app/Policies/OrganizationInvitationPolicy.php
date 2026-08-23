<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\OrganizationInvitation;
use App\Models\User;
use App\Support\OrganizationContext;

/**
 * Two gates, always: the invitation belongs to the bound organization, and the
 * user holds the permission.
 */
final readonly class OrganizationInvitationPolicy
{
    public function __construct(private OrganizationContext $context) {}

    public function view(User $user, OrganizationInvitation $invitation): bool
    {
        return $this->context->id() === $invitation->organization_id && $user->can('members.view');
    }

    public function update(User $user, OrganizationInvitation $invitation): bool
    {
        return $this->context->id() === $invitation->organization_id && $user->can('members.invite');
    }

    public function delete(User $user, OrganizationInvitation $invitation): bool
    {
        return $this->context->id() === $invitation->organization_id && $user->can('members.invite');
    }
}
