<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Organization;
use App\Models\User;
use App\Support\OrganizationContext;

/**
 * Two gates, always: the record has to be in the caller's organization, and the
 * caller has to hold the permission for the verb. Either one alone leaves a
 * hole — see .ai/rules/authorization.md.
 */
final readonly class OrganizationPolicy
{
    public function __construct(private OrganizationContext $context) {}

    public function viewAny(User $user): bool
    {
        return $this->context->id() !== null && $user->can('organization.view');
    }

    public function view(User $user, Organization $organization): bool
    {
        return $this->context->id() === $organization->id && $user->can('organization.view');
    }

    public function update(User $user, Organization $organization): bool
    {
        return $this->context->id() === $organization->id && $user->can('organization.update');
    }

    public function delete(User $user, Organization $organization): bool
    {
        return $this->context->id() === $organization->id && $user->can('organization.delete');
    }
}
