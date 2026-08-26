<?php

declare(strict_types=1);

use App\Models\Organization;
use App\Models\OrganizationInvitation;
use App\Models\User;
use App\Notifications\OrganizationInvitationNotification;
use App\Support\OrganizationContext;
use Illuminate\Support\Facades\Notification;

it('lists the pending invitations', function (): void {
    $organization = Organization::factory()->create();
    $owner = User::factory()->forOrganization($organization)->create();
    OrganizationInvitation::factory()->create(['organization_id' => $organization->id]);
    OrganizationInvitation::factory()->create([
        'organization_id' => $organization->id,
        'accepted_at' => now(),
    ]);

    $this->actingAs($owner)
        ->get(route('organization-invitation.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('organization-invitation/index')
            ->has('invitations.rows', 1)
            ->where('invitations.total', 1));
});

it('refuses the pending invitations without the permission', function (): void {
    $organization = Organization::factory()->create();
    $user = User::factory()->forOrganization($organization)->create();

    resolve(OrganizationContext::class)->runAs($organization, fn () => $user->syncRoles([]));

    $this->actingAs($user)
        ->get(route('organization-invitation.index'))
        ->assertForbidden();
});

it('renders the invite page', function (): void {
    $organization = Organization::factory()->create();
    $owner = User::factory()->forOrganization($organization)->create();

    $this->actingAs($owner)
        ->get(route('organization-invitation.create'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('organization-invitation/create')
            ->has('roles', 3));
});

it('sends an invitation', function (): void {
    Notification::fake();

    $organization = Organization::factory()->create();
    $owner = User::factory()->forOrganization($organization)->create();

    $this->actingAs($owner)
        ->fromRoute('organization-invitation.create')
        ->post(route('organization-invitation.store'), [
            'email' => 'new@example.com',
            'role' => 'Member',
        ])
        ->assertRedirectToRoute('organization-invitation.index');

    expect(OrganizationInvitation::withoutOrganizationScope()->where('email', 'new@example.com')->exists())->toBeTrue();

    Notification::assertSentOnDemand(OrganizationInvitationNotification::class);
});

it('requires a valid email to invite', function (): void {
    $organization = Organization::factory()->create();
    $owner = User::factory()->forOrganization($organization)->create();

    $this->actingAs($owner)
        ->fromRoute('organization-invitation.create')
        ->post(route('organization-invitation.store'), ['email' => 'nope', 'role' => 'Member'])
        ->assertSessionHasErrors('email');
});

it('refuses to invite without the permission', function (): void {
    $organization = Organization::factory()->create();
    $user = User::factory()->forOrganization($organization, 'Member')->create();

    $this->actingAs($user)
        ->fromRoute('organization-invitation.create')
        ->post(route('organization-invitation.store'), ['email' => 'new@example.com', 'role' => 'Member'])
        ->assertForbidden();
});

it('refuses the invite page without the permission', function (): void {
    $organization = Organization::factory()->create();
    $user = User::factory()->forOrganization($organization, 'Member')->create();

    $this->actingAs($user)
        ->get(route('organization-invitation.create'))
        ->assertForbidden();
});

it('revokes an invitation', function (): void {
    $organization = Organization::factory()->create();
    $owner = User::factory()->forOrganization($organization)->create();
    $invitation = OrganizationInvitation::factory()->create(['organization_id' => $organization->id]);

    $this->actingAs($owner)
        ->fromRoute('organization-invitation.index')
        ->delete(route('organization-invitation.destroy', $invitation))
        ->assertRedirectToRoute('organization-invitation.index');

    expect(OrganizationInvitation::withoutOrganizationScope()->count())->toBe(0);
});

it('cannot even see an invitation of another organization', function (): void {
    $organization = Organization::factory()->create();
    $owner = User::factory()->forOrganization($organization)->create();
    $invitation = OrganizationInvitation::factory()->create();

    $this->actingAs($owner)
        ->fromRoute('organization-invitation.index')
        ->delete(route('organization-invitation.destroy', $invitation))
        ->assertNotFound();

    expect(OrganizationInvitation::withoutOrganizationScope()->count())->toBe(1);
});
