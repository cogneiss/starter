<?php

declare(strict_types=1);

use App\Enums\MembershipStatus;
use App\Models\Organization;
use App\Models\OrganizationMembership;
use App\Models\User;

beforeEach(function (): void {
    $this->organization = Organization::factory()->create();
    $this->owner = User::factory()->forOrganization($this->organization)->create(['name' => 'Zoe']);

    // Twelve more than fits on the first page of ten, so "the page" and "every
    // matching record" are different numbers and the test can tell them apart.
    // Named in order, so which rows the first page holds is not a guess.
    for ($index = 0; $index < 12; $index++) {
        User::factory()->forOrganization($this->organization, 'Member')
            ->create(['name' => sprintf('Member %02d', $index)]);
    }

    $this->ids = $this->organization->memberships()->pluck('id')->all();

    $this->memberIds = $this->organization->memberships()
        ->where('user_id', '!=', $this->owner->id)
        ->pluck('id')
        ->all();

    $this->suspendedMembers = fn (): int => OrganizationMembership::query()
        ->whereKey($this->memberIds)
        ->where('status', MembershipStatus::Suspended)
        ->count();
});

/**
 * A tick box that says "select all" means the rows the person is looking at.
 * Reaching past the page is a different request, and the server does not infer
 * it from a long list of ids: someone who scrolled through five pages ticking
 * boxes must still say so explicitly before the sixth page moves.
 *
 * The sort is named so the page is a fixed set of rows rather than whatever the
 * default ordering happened to put first.
 */
it('touches only the page when the request does not opt in to every matching record', function (): void {
    expect($this->ids)->toHaveCount(13);

    $this->actingAs($this->owner)
        ->fromRoute('organization-member.edit')
        ->post(route('organization-member.bulk').'?sort=user.name&dir=asc', [
            'action' => 'suspend',
            'ids' => $this->ids,
        ])
        ->assertRedirectToRoute('organization-member.edit');

    expect(($this->suspendedMembers)())->toBe(10);
});

it('touches every matching record when the request opts in', function (): void {
    $this->actingAs($this->owner)
        ->fromRoute('organization-member.edit')
        ->post(route('organization-member.bulk'), [
            'action' => 'suspend',
            'ids' => [],
            'all' => true,
        ])
        ->assertRedirectToRoute('organization-member.edit');

    expect(($this->suspendedMembers)())->toBe(12);

    // The one record the action itself refuses is refused on its own; it does
    // not take the other twelve down with it.
    expect($this->organization->memberships()->where('user_id', $this->owner->id)->sole()->status)
        ->toBe(MembershipStatus::Active);
});

it('opts in only to the records the filters leave', function (): void {
    OrganizationMembership::query()
        ->whereKey($this->memberIds[0])
        ->sole()
        ->forceFill(['status' => MembershipStatus::Suspended])
        ->save();

    $this->actingAs($this->owner)
        ->fromRoute('organization-member.edit')
        ->post(route('organization-member.bulk').'?'.http_build_query(['f' => ['status' => 'suspended']]), [
            'action' => 'reactivate',
            'ids' => [],
            'all' => true,
        ])
        ->assertRedirectToRoute('organization-member.edit');

    expect(($this->suspendedMembers)())->toBe(0);
});
