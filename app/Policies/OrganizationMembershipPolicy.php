<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\OrganizationMembership;
use App\Models\User;
use App\Support\OrganizationContext;

final readonly class OrganizationMembershipPolicy
{
    public function __construct(private OrganizationContext $context) {}

    public function viewAny(User $user): bool
    {
        return $this->context->id() !== null && $user->can('members.view');
    }

    public function view(User $user, OrganizationMembership $membership): bool
    {
        return $this->context->id() === $membership->organization_id && $user->can('members.view');
    }

    public function update(User $user, OrganizationMembership $membership): bool
    {
        return $this->context->id() === $membership->organization_id && $user->can('members.update');
    }

    /**
     * Removing someone else is administration; removing yourself is leaving, and
     * it is not what the members screen is for. Keeping the two apart also means
     * the last administrator cannot delete their own way out of the organization
     * by ticking their own row.
     */
    public function delete(User $user, OrganizationMembership $membership): bool
    {
        return $this->context->id() === $membership->organization_id
            && $user->id !== $membership->user_id
            && $user->can('members.remove');
    }
}
