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

    /**
     * Whether the caller may put somebody into this role.
     *
     * Handing out a protected role is handing out the organization itself, so
     * it takes the organization permission rather than the invite one. This is
     * the ability the bulk importer asks per row: one file can name two roles
     * the same person is answered differently about.
     */
    public function grant(User $user, Role $role): bool
    {
        return $this->context->id() === $role->organization_id
            && $user->can($role->protected ? 'organization.update' : 'members.invite');
    }

    public function delete(User $user, Role $role): bool
    {
        return $this->context->id() === $role->organization_id && ! $role->protected && $user->can('roles.manage');
    }
}
