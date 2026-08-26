<?php

declare(strict_types=1);

use App\Http\Controllers\Concerns\ListsResources;
use App\Http\Controllers\OrganizationInvitationController;
use App\Http\Controllers\OrganizationMemberController;
use App\Models\Organization;
use App\Models\OrganizationInvitation;
use App\Models\User;

it('is the kit both list screens are actually built on', function (string $controller): void {
    // Reflection, not a grep: a mention in a comment or a stale import would
    // pass a text search, and neither would put the scope on the query.
    expect(class_uses_recursive($controller))->toContain(ListsResources::class);
})->with([
    OrganizationMemberController::class,
    OrganizationInvitationController::class,
]);

it('leaves the members of another organization out of the list', function (): void {
    $organization = Organization::factory()->create();
    $owner = User::factory()->forOrganization($organization)->create();

    $other = Organization::factory()->create();
    $stranger = User::factory()->forOrganization($other)->create(['email' => 'stranger@example.com']);

    $this->actingAs($owner)
        ->get(route('organization-member.edit'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('members.rows', 1)
            ->where('members.total', 1)
            ->where('members.rows.0.email', $owner->email));

    expect($stranger->email)->toBe('stranger@example.com');
});

it('leaves the invitations of another organization out of the list', function (): void {
    $organization = Organization::factory()->create();
    $owner = User::factory()->forOrganization($organization)->create();
    $mine = OrganizationInvitation::factory()->create(['organization_id' => $organization->id]);

    $other = Organization::factory()->create();
    OrganizationInvitation::factory()->create(['organization_id' => $other->id]);

    $this->actingAs($owner)
        ->get(route('organization-invitation.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('invitations.rows', 1)
            ->where('invitations.total', 1)
            ->where('invitations.rows.0.email', $mine->email));
});

it('does not reveal that a foreign membership exists, even to an owner', function (): void {
    $organization = Organization::factory()->create();
    $owner = User::factory()->forOrganization($organization)->create();

    $other = Organization::factory()->create();
    $stranger = User::factory()->forOrganization($other, 'Member')->create();
    $foreign = $other->memberships()->where('user_id', $stranger->id)->sole();

    $response = $this->actingAs($owner)
        ->fromRoute('organization-member.edit')
        ->patch(route('organization-member.update', $foreign), ['role' => 'Admin']);

    // A foreign id must look like an id that does not exist. 403 would confirm it does.
    $response->assertNotFound();
    expect($response->getStatusCode())->toBe(404)->not->toBe(403);
});

it('does not reveal that a foreign invitation exists, even to an owner', function (): void {
    $organization = Organization::factory()->create();
    $owner = User::factory()->forOrganization($organization)->create();

    $other = Organization::factory()->create();
    $foreign = OrganizationInvitation::factory()->create(['organization_id' => $other->id]);

    $response = $this->actingAs($owner)
        ->fromRoute('organization-invitation.index')
        ->delete(route('organization-invitation.destroy', $foreign));

    $response->assertNotFound();
    expect($response->getStatusCode())->toBe(404)->not->toBe(403);

    expect(OrganizationInvitation::withoutOrganizationScope()->whereKey($foreign->id)->exists())->toBeTrue();
});
