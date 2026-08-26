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

    public function delete(User $user, OrganizationMembership $membership): bool
    {
        return $this->context->id() === $membership->organization_id && $user->can('members.remove');
    }
}
