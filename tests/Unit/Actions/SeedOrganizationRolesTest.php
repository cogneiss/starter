<?php

declare(strict_types=1);

use App\Actions\SeedOrganizationRoles;
use App\Models\Organization;
use App\Models\Role;
use App\Models\RoleTemplate;
use App\Support\PermissionCatalog;

it('clones every template into roles owned by the organization', function (): void {
    $organization = Organization::factory()->create();

    $roles = resolve(SeedOrganizationRoles::class)->handle($organization);

    expect(array_keys($roles))->toBe(['Admin', 'Member', 'Owner'])
        ->and($roles['Owner']->organization_id)->toBe($organization->id)
        ->and($roles['Owner']->protected)->toBeTrue()
        ->and($roles['Owner']->permissions->pluck('name')->sort()->values()->all())
        ->toBe(collect(PermissionCatalog::names())->sort()->values()->all())
        ->and($roles['Admin']->permissions->pluck('name')->all())->not->toContain('organization.delete')
        ->and($roles['Member']->permissions->pluck('name')->sort()->values()->all())
        ->toBe(collect(PermissionCatalog::endingWith('view'))->sort()->values()->all());
});

it('gives each organization its own roles', function (): void {
    $first = Organization::factory()->create();
    $second = Organization::factory()->create();

    $firstRoles = resolve(SeedOrganizationRoles::class)->handle($first);
    $secondRoles = resolve(SeedOrganizationRoles::class)->handle($second);

    $firstRoles['Member']->syncPermissions([]);

    expect($firstRoles['Member']->id)->not->toBe($secondRoles['Member']->id)
        ->and($firstRoles['Member']->fresh()?->permissions)->toBeEmpty()
        ->and($secondRoles['Member']->fresh()?->permissions)->not->toBeEmpty();
});

it('skips a template permission the catalog no longer knows about', function (): void {
    $template = RoleTemplate::query()->where('name', 'Member')->firstOrFail();

    $template->forceFill(['permissions' => [...$template->permissions, 'widgets.create']])->save();

    $organization = Organization::factory()->create();

    $roles = resolve(SeedOrganizationRoles::class)->handle($organization);

    expect($roles['Member']->permissions->pluck('name')->sort()->values()->all())
        ->toBe(collect(PermissionCatalog::endingWith('view'))->sort()->values()->all());
});

it('is safe to run twice', function (): void {
    $organization = Organization::factory()->create();

    resolve(SeedOrganizationRoles::class)->handle($organization);
    resolve(SeedOrganizationRoles::class)->handle($organization);

    expect(Role::query()->where('organization_id', $organization->id)->count())->toBe(3);
});
