<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\RoleTemplate;
use App\Support\PermissionCatalog;
use Illuminate\Database\Seeder;

/**
 * The three roles every new organization starts with. Safe to re-run.
 */
final class RoleTemplateSeeder extends Seeder
{
    public function run(): void
    {
        $all = PermissionCatalog::names();

        RoleTemplate::query()->updateOrCreate(['name' => 'Owner'], [
            'description' => 'Full control of the organization, including deleting it.',
            'permissions' => $all,
            'is_default' => false,
            'protected' => true,
        ]);

        RoleTemplate::query()->updateOrCreate(['name' => 'Admin'], [
            'description' => 'Runs the organization day to day but cannot delete it.',
            'permissions' => array_values(array_diff($all, ['organization.delete'])),
            'is_default' => false,
            'protected' => false,
        ]);

        RoleTemplate::query()->updateOrCreate(['name' => 'Member'], [
            'description' => 'Read-only access to the organization and its members.',
            'permissions' => PermissionCatalog::endingWith('view'),
            'is_default' => true,
            'protected' => false,
        ]);
    }
}
