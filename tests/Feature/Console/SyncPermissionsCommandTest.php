<?php

declare(strict_types=1);

use App\Support\PermissionCatalog;
use Spatie\Permission\Models\Permission;

it('creates the permissions the catalog defines', function (): void {
    $this->artisan('app:sync-permissions')
        ->expectsOutputToContain('organization.view')
        ->assertSuccessful();

    expect(Permission::query()->pluck('name')->sort()->values()->all())
        ->toBe(collect(PermissionCatalog::names())->sort()->values()->all());
});

it('reports permissions the catalog no longer knows about', function (): void {
    Permission::query()->create([
        'name' => 'legacy.verb',
        'guard_name' => config()->string('auth.defaults.guard'),
    ]);

    $this->artisan('app:sync-permissions')
        ->expectsOutputToContain('legacy.verb')
        ->assertSuccessful();

    expect(Permission::query()->where('name', 'legacy.verb')->exists())->toBeTrue();
});

it('says nothing needs doing when everything is in sync', function (): void {
    $this->artisan('app:sync-permissions')->assertSuccessful();

    $this->artisan('app:sync-permissions')
        ->expectsOutputToContain('already in sync')
        ->assertSuccessful();
});
