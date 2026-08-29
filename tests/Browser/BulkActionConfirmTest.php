<?php

declare(strict_types=1);

use App\Models\Organization;
use App\Models\OrganizationMembership;
use App\Models\User;

/**
 * A destructive bulk action is the one place where a mis-click is not
 * recoverable, so the dialog has to be a real gate rather than a notice: while
 * it is open nothing has happened yet, and dismissing it leaves the record
 * exactly where it was.
 *
 * The record count is read straight from the database between each step, so
 * "nothing was deleted" is a fact about the table rather than about what the
 * screen chose to render.
 */
it('deletes nothing until the confirm dialog is answered', function (): void {
    $organization = Organization::factory()->create();

    $this->actingAs(User::factory()->forOrganization($organization)->create([
        'name' => 'Aaron Owner',
    ]));

    $member = User::factory()->forOrganization($organization, 'Member')->create([
        'name' => 'Beth Member',
        'email' => 'beth@example.com',
    ]);

    $membership = $organization->memberships()->where('user_id', $member->id)->sole();

    $exists = fn (): bool => OrganizationMembership::query()->whereKey($membership->id)->exists();

    $page = visit('/settings/members')->wait(1);

    // Ticking a row and applying a destructive action opens the dialog and does
    // nothing else.
    $page->click(sprintf('[data-test="select-%s"]', $membership->id))
        ->assertPresent('[data-test="bulk-bar"]')
        ->select('[data-test="bulk-action"]', 'remove')
        ->click('[data-test="bulk-apply"]')
        ->assertPresent('[data-test="confirm-dialog"]');

    expect($exists())->toBeTrue();

    // Backing out leaves the record alone.
    $page->click('[data-test="confirm-cancel"]')
        ->assertMissing('[data-test="confirm-dialog"]');

    expect($exists())->toBeTrue();

    // Saying yes is the only thing that removes anyone.
    $page->click('[data-test="bulk-apply"]')
        ->assertPresent('[data-test="confirm-dialog"]')
        ->click('[data-test="confirm-proceed"]')
        ->waitForText('Aaron Owner')
        ->assertDontSeeIn('[data-test="table-body"]', 'Beth Member')
        ->assertNoJavaScriptErrors();

    expect($exists())->toBeFalse();
});
