<?php

declare(strict_types=1);

namespace App\Actions;

use App\Support\PermissionCatalog;
use Spatie\Permission\Models\Permission;

final readonly class SyncPermissions
{
    /**
     * Create any permission in the catalog that is missing, and report the ones
     * in the database that the catalog no longer knows about. Nothing is
     * deleted: an orphan may still be attached to a role someone relies on.
     *
     * @return array{created: list<string>, orphaned: list<string>}
     */
    public function handle(): array
    {
        $guard = config()->string('auth.defaults.guard');

        /** @var list<string> $existing */
        $existing = Permission::query()
            ->where('guard_name', $guard)
            ->pluck('name')
            ->all();

        $created = array_values(array_diff(PermissionCatalog::names(), $existing));

        foreach ($created as $name) {
            Permission::query()->create([
                'name' => $name,
                'guard_name' => $guard,
            ]);
        }

        return [
            'created' => $created,
            'orphaned' => array_values(array_diff($existing, PermissionCatalog::names())),
        ];
    }
}
