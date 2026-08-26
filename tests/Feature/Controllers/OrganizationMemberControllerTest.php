<?php

declare(strict_types=1);

use App\Enums\MembershipStatus;
use App\Models\Organization;
use App\Models\OrganizationMembership;
use App\Models\User;
use App\Support\OrganizationContext;

it('renders the members page', function (): void {
    $organization = Organization::factory()->create();
    $owner = User::factory()->forOrganization($organization)->create(['name' => 'Ada']);
    $member = User::factory()->forOrganization($organization, 'Member')->create(['name' => 'Zoe']);

    $this->actingAs($owner)
        ->get(route('organization-member.edit'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('organization-member/edit')
            ->has('members.rows', 2)
            ->where('members.total', 2)
            ->has('roles', 3)
            ->where('members.rows.1.email', $member->email));
});

it('refuses the members page without the permission', function (): void {
    $organization = Organization::factory()->create();
    $user = User::factory()->forOrganization($organization)->create();

    resolve(OrganizationContext::class)->runAs($organization, fn () => $user->syncRoles([]));

    $this->actingAs($user)
        ->get(route('organization-member.edit'))
        ->assertForbidden();
});

it('changes a member role', function (): void {
    $organization = Organization::factory()->create();
    $owner = User::factory()->forOrganization($organization)->create();
    $member = User::factory()->forOrganization($organization, 'Member')->create();
    $membership = $organization->memberships()->where('user_id', $member->id)->sole();

    $this->actingAs($owner)
        ->fromRoute('organization-member.edit')
        ->patch(route('organization-member.update', $membership), ['role' => 'Admin'])
        ->assertRedirectToRoute('organization-member.edit');

    resolve(OrganizationContext::class)->runAs($organization, function () use ($member): void {
        expect($member->fresh()?->hasRole('Admin'))->toBeTrue();
    });
});

it('suspends and reactivates a member', function (): void {
    $organization = Organization::factory()->create();
    $owner = User::factory()->forOrganization($organization)->create();
    $member = User::factory()->forOrganization($organization, 'Member')->create();
    $membership = $organization->memberships()->where('user_id', $member->id)->sole();

    $this->actingAs($owner)
        ->fromRoute('organization-member.edit')
        ->patch(route('organization-member.update', $membership), ['status' => 'suspended'])
        ->assertRedirectToRoute('organization-member.edit');

    expect($membership->refresh()->status)->toBe(MembershipStatus::Suspended);

    $this->actingAs($owner)
        ->fromRoute('organization-member.edit')
        ->patch(route('organization-member.update', $membership), ['status' => 'active']);

    expect($membership->refresh()->status)->toBe(MembershipStatus::Active);
});

it('validates the membership status', function (): void {
    $organization = Organization::factory()->create();
    $owner = User::factory()->forOrganization($organization)->create();
    $membership = $organization->memberships()->sole();

    $this->actingAs($owner)
        ->fromRoute('organization-member.edit')
        ->patch(route('organization-member.update', $membership), ['status' => 'banished'])
        ->assertSessionHasErrors('status');
});

it('removes a member', function (): void {
    $organization = Organization::factory()->create();
    $owner = User::factory()->forOrganization($organization)->create();
    $member = User::factory()->forOrganization($organization, 'Member')->create();
    $membership = $organization->memberships()->where('user_id', $member->id)->sole();

    $this->actingAs($owner)
        ->fromRoute('organization-member.edit')
        ->delete(route('organization-member.destroy', $membership))
        ->assertRedirectToRoute('organization-member.edit');

    expect(OrganizationMembership::query()->whereKey($membership->id)->exists())->toBeFalse();
});

it('does not admit that a membership of another organization exists', function (): void {
    $organization = Organization::factory()->create();
    $owner = User::factory()->forOrganization($organization)->create();

    $other = Organization::factory()->create();
    $otherMember = User::factory()->forOrganization($other)->create();
    $otherMembership = $other->memberships()->where('user_id', $otherMember->id)->sole();

    $this->actingAs($owner)
        ->fromRoute('organization-member.edit')
        ->delete(route('organization-member.destroy', $otherMembership))
        ->assertNotFound();
});
