<?php

declare(strict_types=1);

use App\Models\Organization;
use App\Models\User;
use App\Support\OrganizationContext;
use Inertia\Support\SessionKey;

it('renders the organization creation page', function (): void {
    $response = $this->actingAs(User::factory()->create())
        ->get(route('organization.create'));

    $response->assertOk()
        ->assertInertia(fn ($page) => $page->component('organization/create'));
});

it('may create an organization', function (): void {
    $user = User::factory()->create();

    $response = $this->actingAs($user)
        ->fromRoute('organization.create')
        ->post(route('organization.store'), ['name' => 'Acme Inc.']);

    $response->assertRedirectToRoute('dashboard')
        ->assertSessionHas(SessionKey::FLASH_DATA, [
            'toast' => [
                'type' => 'success',
                'message' => __('Organization created.'),
            ],
        ]);

    expect($user->refresh()->currentOrganization?->name)->toBe('Acme Inc.');
});

it('requires a name to create an organization', function (): void {
    $this->actingAs(User::factory()->create())
        ->fromRoute('organization.create')
        ->post(route('organization.store'), ['name' => ''])
        ->assertSessionHasErrors('name');
});

it('renders the organization settings page', function (): void {
    $organization = Organization::factory()->create(['name' => 'Acme Inc.']);
    $user = User::factory()->forOrganization($organization)->create();

    $response = $this->actingAs($user)->get(route('organization.edit'));

    $response->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('organization/edit')
            ->where('organization.name', 'Acme Inc.'));
});

it('may update the organization', function (): void {
    $organization = Organization::factory()->create();
    $user = User::factory()->forOrganization($organization)->create();

    $response = $this->actingAs($user)
        ->fromRoute('organization.edit')
        ->patch(route('organization.update'), [
            'name' => 'Renamed Inc.',
            'slug' => 'renamed-inc',
        ]);

    $response->assertRedirectToRoute('organization.edit')
        ->assertSessionHas(SessionKey::FLASH_DATA, [
            'toast' => [
                'type' => 'success',
                'message' => __('Organization updated.'),
            ],
        ]);

    expect($organization->refresh()->name)->toBe('Renamed Inc.')
        ->and($organization->slug)->toBe('renamed-inc');
});

it('rejects a slug already taken by another organization', function (): void {
    Organization::factory()->create(['slug' => 'taken']);

    $organization = Organization::factory()->create();
    $user = User::factory()->forOrganization($organization)->create();

    $this->actingAs($user)
        ->fromRoute('organization.edit')
        ->patch(route('organization.update'), [
            'name' => 'Acme',
            'slug' => 'taken',
        ])
        ->assertSessionHasErrors('slug');
});

it('sends a user without an organization to the creation page', function (): void {
    $this->actingAs(User::factory()->create())
        ->get(route('organization.edit'))
        ->assertRedirectToRoute('organization.create');
});

it('refuses to show the settings page without the view permission', function (): void {
    $organization = Organization::factory()->create();
    $user = User::factory()->forOrganization($organization, 'Member')->create();

    resolve(OrganizationContext::class)->runAs($organization, fn () => $user->syncRoles([]));

    $this->actingAs($user)->get(route('organization.edit'))->assertForbidden();
});

it('refuses to update the organization without the update permission', function (): void {
    $organization = Organization::factory()->create(['name' => 'Acme Inc.']);
    $user = User::factory()->forOrganization($organization, 'Member')->create();

    $this->actingAs($user)
        ->patch(route('organization.update'), ['name' => 'Renamed', 'slug' => 'renamed'])
        ->assertForbidden();

    expect($organization->refresh()->name)->toBe('Acme Inc.');
});
