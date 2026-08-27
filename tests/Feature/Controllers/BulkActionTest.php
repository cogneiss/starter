<?php

declare(strict_types=1);

use App\Enums\MembershipStatus;
use App\Models\Organization;
use App\Models\OrganizationMembership;
use App\Models\Role;
use App\Models\User;
use App\Support\OrganizationContext;
use Inertia\Support\SessionKey;

function memberNamed(Organization $organization, string $name): OrganizationMembership
{
    $user = User::factory()->forOrganization($organization, 'Member')->create(['name' => $name]);

    return $organization->memberships()->where('user_id', $user->id)->sole();
}

beforeEach(function (): void {
    $this->organization = Organization::factory()->create();
    $this->owner = User::factory()->forOrganization($this->organization)->create(['name' => 'Ada']);

    $this->first = memberNamed($this->organization, 'Bo');
    $this->second = memberNamed($this->organization, 'Cy');

    $this->ownMembership = $this->organization->memberships()
        ->where('user_id', $this->owner->id)
        ->sole();
});

/**
 * The record the acting person may not touch is their own: removing yourself is
 * leaving, which the members screen does not do. The other two still go, and the
 * third comes back named — an administrator who ticked three rows and saw "2
 * removed" with no further word would reasonably assume all three were gone.
 */
it('applies a bulk action to the records the policy allows and names the ones it refuses', function (): void {
    $response = $this->actingAs($this->owner)
        ->fromRoute('organization-member.edit')
        ->post(route('organization-member.bulk'), [
            'action' => 'remove',
            'ids' => [$this->first->id, $this->second->id, $this->ownMembership->id],
        ]);

    $response->assertRedirectToRoute('organization-member.edit');

    expect(OrganizationMembership::query()->whereKey([$this->first->id, $this->second->id])->count())->toBe(0);
    expect(OrganizationMembership::query()->whereKey($this->ownMembership->id)->exists())->toBeTrue();

    $flash = session(SessionKey::FLASH_DATA);

    expect($flash['bulk'])->toHaveCount(3);

    $refused = collect($flash['bulk'])->firstWhere('id', (string) $this->ownMembership->id);

    expect($refused)->toBe([
        'id' => (string) $this->ownMembership->id,
        'label' => 'Ada',
        'status' => 'refused',
    ]);

    expect($flash['toast']['message'])->toContain('Ada');
});

/**
 * Someone who may read the members screen is not thereby allowed to empty it.
 * The gate on the route says only that they may work with members at all; the
 * policy is still asked about every record, and a record it refuses comes back
 * named rather than quietly missing from the count.
 */
it('reports a record the policy refuses and leaves it alone', function (): void {
    $viewer = User::factory()->forOrganization($this->organization, 'Member')->create(['name' => 'Di']);

    resolve(OrganizationContext::class)->runAs($this->organization, function (): void {
        Role::query()
            ->where('organization_id', $this->organization->id)
            ->where('name', 'Member')
            ->sole()
            ->syncPermissions(['members.view']);
    });

    $this->actingAs($viewer)
        ->fromRoute('organization-member.edit')
        ->post(route('organization-member.bulk'), [
            'action' => 'remove',
            'ids' => [$this->first->id],
        ])
        ->assertRedirectToRoute('organization-member.edit');

    expect(OrganizationMembership::query()->whereKey($this->first->id)->exists())->toBeTrue();

    expect(session(SessionKey::FLASH_DATA)['bulk'])->toBe([[
        'id' => (string) $this->first->id,
        'label' => 'Bo',
        'status' => 'unauthorized',
    ]]);
});

it('suspends a selection', function (): void {
    $this->actingAs($this->owner)
        ->fromRoute('organization-member.edit')
        ->post(route('organization-member.bulk'), [
            'action' => 'suspend',
            'ids' => [$this->first->id, $this->second->id],
        ])
        ->assertRedirectToRoute('organization-member.edit');

    expect($this->first->refresh()->status)->toBe(MembershipStatus::Suspended)
        ->and($this->second->refresh()->status)->toBe(MembershipStatus::Suspended);
});

it('reactivates a selection', function (): void {
    $this->first->forceFill(['status' => MembershipStatus::Suspended])->save();

    $this->actingAs($this->owner)
        ->fromRoute('organization-member.edit')
        ->post(route('organization-member.bulk'), [
            'action' => 'reactivate',
            'ids' => [$this->first->id],
        ])
        ->assertRedirectToRoute('organization-member.edit');

    expect($this->first->refresh()->status)->toBe(MembershipStatus::Active);
});

it('refuses an action it does not know', function (): void {
    $this->actingAs($this->owner)
        ->fromRoute('organization-member.edit')
        ->post(route('organization-member.bulk'), ['action' => 'banish', 'ids' => []])
        ->assertSessionHasErrors('action');
});

it('refuses a bulk action from someone who may not see members', function (): void {
    $stranger = User::factory()->forOrganization($this->organization, 'Member')->create();

    resolve(OrganizationContext::class)
        ->runAs($this->organization, fn () => $stranger->syncRoles([]));

    $this->actingAs($stranger)
        ->fromRoute('organization-member.edit')
        ->post(route('organization-member.bulk'), ['action' => 'suspend', 'ids' => []])
        ->assertForbidden();
});
