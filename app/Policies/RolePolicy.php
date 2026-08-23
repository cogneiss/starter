<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Role;
use App\Models\User;
use App\Support\OrganizationContext;

final readonly class RolePolicy
{
    public function __construct(private OrganizationContext $context) {}

    public function view(User $user, Role $role): bool
    {
        return $this->context->id() === $role->organization_id && $user->can('roles.view');
    }

    /**
     * Protected roles are the ones an organization cannot function without, so
     * they are read-only however many permissions the caller holds.
     */
    public function update(User $user, Role $role): bool
    {
        return $this->context->id() === $role->organization_id && ! $role->protected && $user->can('roles.manage');
    }

    public function delete(User $user, Role $role): bool
    {
        return $this->context->id() === $role->organization_id && ! $role->protected && $user->can('roles.manage');
    }
}
