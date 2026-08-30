<?php

declare(strict_types=1);

namespace App\Actions;

use App\Models\Organization;
use App\Models\Role;
use App\Models\RoleTemplate;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;

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

                $role->syncPermissions($this->existing($template->permissions, $guard));

                $roles[$template->name] = $role;
            }

            return $roles;
        });
    }

    /**
     * The permissions among these names that the application still has.
     *
     * A template written against an older catalog still names a permission that
     * no longer exists, and one stale entry must not stop an organization being
     * created.
     *
     * @param  list<string>  $names
     * @return Collection<int, Permission>
     */
    private function existing(array $names, string $guard): Collection
    {
        return Permission::query()
            ->where('guard_name', $guard)
            ->whereIn('name', $names)
            ->get();
    }
}
