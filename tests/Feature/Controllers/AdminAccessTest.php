<?php

declare(strict_types=1);

use App\Actions\SeedOrganizationRoles;
use App\Admin\AdminResources;
use App\Models\Organization;
use App\Models\User;
use App\Support\OrganizationContext;

it('lets a platform admin into the control plane', function (): void {
    $admin = User::factory()->superAdmin()->create();

    $this->actingAs($admin)
        ->get(route('admin.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('admin/health'));
});

it('turns an organization owner holding every organization role away with a 404', function (): void {
    $organization = Organization::factory()->create();
    $owner = User::factory()->forOrganization($organization)->create();

    $roles = resolve(SeedOrganizationRoles::class)->handle($organization);

    resolve(OrganizationContext::class)->runAs($organization, function () use ($owner, $roles): void {
        foreach ($roles as $role) {
            $owner->assignRole($role);
        }
    });

    $this->actingAs($owner)->get(route('admin.index'))->assertNotFound();

    foreach (AdminResources::keys() as $key) {
        $this->actingAs($owner)
            ->get(route('admin.pages', ['page' => $key]))
            ->assertNotFound();
    }
});

it('turns a guest away to the login screen', function (): void {
    $this->get(route('admin.index'))->assertRedirect(route('login'));
});

it('answers 404 for a page outside the declared set', function (): void {
    $admin = User::factory()->superAdmin()->create();

    $this->actingAs($admin)
        ->get(route('admin.pages', ['page' => 'nonexistent']))
        ->assertNotFound();
});
