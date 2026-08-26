<?php

declare(strict_types=1);

use App\Models\Organization;
use App\Models\OrganizationInvitation;
use App\Models\OrganizationMembership;
use App\Models\User;

// Every record below belongs to an organization the caller is not acting as.
// None of them may appear, and none of them may be loaded and then dropped: the
// scope is a where clause on the resource's query.
it('returns nothing from an organization the caller is not acting as', function (): void {
    $organization = Organization::factory()->create();
    $owner = User::factory()->forOrganization($organization)->create();

    $other = Organization::factory()->create(['name' => 'Halcyon Freight']);
    User::factory()->forOrganization($other, 'Member')->create(['name' => 'Halcyon Delacroix']);
    OrganizationInvitation::factory()->create([
        'organization_id' => $other->id,
        'email' => 'halcyon@example.test',
    ]);

    $this->actingAs($owner)
        ->getJson(route('search', ['q' => 'Halcyon']))
        ->assertOk()
        ->assertExactJson(['groups' => []]);
});

// A member of both organizations sees only the one they are acting as, so the
// scope follows the active organization rather than the caller's memberships.
it('follows the acting organization for a member of two', function (): void {
    $organization = Organization::factory()->create();
    $user = User::factory()->forOrganization($organization)->create();

    $other = Organization::factory()->create();
    OrganizationMembership::factory()->create([
        'organization_id' => $other->id,
        'user_id' => $user->id,
    ]);
    OrganizationInvitation::factory()->create([
        'organization_id' => $other->id,
        'email' => 'halcyon@example.test',
    ]);

    $this->actingAs($user)
        ->getJson(route('search', ['q' => 'halcyon@']))
        ->assertOk()
        ->assertExactJson(['groups' => []]);
});
