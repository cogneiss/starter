<?php

declare(strict_types=1);

namespace App\Actions;

use App\Models\Organization;
use App\Models\Role;
use App\Models\RoleTemplate;
use Illuminate\Support\Facades\DB;

final readonly class SeedOrganizationRoles
{
    public function __construct(private SyncPermissions $permissions) {}

    /**
     * Clone every role template into roles owned by the organization. The roles
     * are copies: editing a template later leaves existing organizations alone.
     *
     * @return array<string, Role> keyed by role name
     */
    public function handle(Organization $organization): array
    {
        $this->permissions->handle();

        $guard = config()->string('auth.defaults.guard');

        return DB::transaction(function () use ($organization, $guard): array {
            $roles = [];

            foreach (RoleTemplate::query()->orderBy('name')->get() as $template) {
                $role = Role::query()->firstOrCreate([
                    'organization_id' => $organization->id,
                    'name' => $template->name,
                    'guard_name' => $guard,
                ], [
                    'protected' => $template->protected,
                ]);

                $role->syncPermissions($template->permissions);

                $roles[$template->name] = $role;
            }

            return $roles;
        });
    }
}
