<?php

declare(strict_types=1);

use App\Models\Organization;
use App\Models\OrganizationInvitation;
use App\Models\User;

it('returns grouped hits for the acting member', function (): void {
    $organization = Organization::factory()->create();
    $owner = User::factory()->forOrganization($organization)->create();

    OrganizationInvitation::factory()->create([
        'organization_id' => $organization->id,
        'email' => 'ines@example.test',
    ]);

    $this->actingAs($owner)
        ->getJson(route('search', ['q' => 'ines@']))
        ->assertOk()
        ->assertJsonPath('groups.0.key', 'organization-invitations')
        ->assertJsonPath('groups.0.label', 'Organization invitations')
        ->assertJsonPath('groups.0.results.0.label', 'ines@example.test')
        ->assertJsonPath('groups.0.results.0.url', route('organization-member.edit'));
});

it('returns no groups for a blank term', function (): void {
    $organization = Organization::factory()->create();
    $owner = User::factory()->forOrganization($organization)->create();

    $this->actingAs($owner)
        ->getJson(route('search'))
        ->assertOk()
        ->assertExactJson(['groups' => []]);
});

it('rejects a term longer than the column it searches', function (): void {
    $organization = Organization::factory()->create();
    $owner = User::factory()->forOrganization($organization)->create();

    $this->actingAs($owner)
        ->getJson(route('search', ['q' => str_repeat('a', 256)]))
        ->assertJsonValidationErrorFor('q');
});

it('refuses a guest', function (): void {
    $this->getJson(route('search', ['q' => 'anything']))->assertUnauthorized();
});
