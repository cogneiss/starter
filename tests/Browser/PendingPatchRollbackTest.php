<?php

declare(strict_types=1);

use App\Models\Organization;
use App\Models\User;
use App\Support\OrganizationContext;

/**
 * An optimistic value is a guess, and a refused patch is the answer that the
 * guess was wrong.
 *
 * Demoting the only owner is refused, so the select goes back to the role the
 * server still holds and the reason arrives on the flash toast every other
 * outcome in this application uses. A silent snap-back would leave the person
 * looking at a row that changed itself for no stated reason.
 */
it('rolls a refused inline edit back and says why on the flash toast', function (): void {
    $organization = Organization::factory()->create();

    $owner = User::factory()->forOrganization($organization)->create([
        'name' => 'Aaron Owner',
    ]);

    $this->actingAs($owner);

    $membership = $organization->memberships()->where('user_id', $owner->id)->sole();

    visit('/settings/members')
        ->wait(1)
        ->assertValue(sprintf('[data-test="role-%s"]', $membership->id), 'Owner')
        ->select(sprintf('[data-test="role-%s"]', $membership->id), 'Member')
        ->waitForText('An organization must keep at least one active owner.')
        ->assertMissing(sprintf('[data-test="patching-%s"]', $membership->id))
        ->assertValue(sprintf('[data-test="role-%s"]', $membership->id), 'Owner')
        ->assertNoJavaScriptErrors();

    resolve(OrganizationContext::class)->runAs($organization, function () use ($owner): void {
        expect($owner->fresh()?->hasRole('Owner'))->toBeTrue();
    });
});
