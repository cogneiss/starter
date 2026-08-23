<?php

declare(strict_types=1);

use App\Models\Organization;
use App\Models\User;

it('switches to another organization the user belongs to', function (): void {
    $current = Organization::factory()->create();
    $other = Organization::factory()->create();

    $user = User::factory()->forOrganization($current)->create();
    $other->memberships()->create([
        'user_id' => $user->id,
        'status' => 'active',
        'joined_at' => now(),
    ]);

    $this->actingAs($user)
        ->fromRoute('dashboard')
        ->put(route('organization-switch.update'), ['organization' => $other->id])
        ->assertRedirectToRoute('dashboard');

    expect($user->refresh()->current_organization_id)->toBe($other->id);
});

it('refuses to switch to an organization the user does not belong to', function (): void {
    $current = Organization::factory()->create();
    $other = Organization::factory()->create();
    $user = User::factory()->forOrganization($current)->create();

    $this->actingAs($user)
        ->fromRoute('dashboard')
        ->put(route('organization-switch.update'), ['organization' => $other->id])
        ->assertForbidden();

    expect($user->refresh()->current_organization_id)->toBe($current->id);
});

it('requires a known organization', function (): void {
    $organization = Organization::factory()->create();
    $user = User::factory()->forOrganization($organization)->create();

    $this->actingAs($user)
        ->fromRoute('dashboard')
        ->put(route('organization-switch.update'), ['organization' => 'missing'])
        ->assertSessionHasErrors('organization');
});
