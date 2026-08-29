<?php

declare(strict_types=1);

use App\Models\Organization;
use App\Models\OrganizationMembership;
use App\Models\User;

/**
 * The confirm dialog is a question, and only one answer means yes.
 *
 * Escape, the cancel control and anything else that closes the dialog all mean
 * no, which is what stops a caller from reading "the dialog went away" as
 * agreement. The record count between the steps is what decides it: the answer
 * is a fact about the table, not about the screen.
 */
beforeEach(function (): void {
    $this->organization = Organization::factory()->create();

    $this->actingAs(User::factory()->forOrganization($this->organization)->create([
        'name' => 'Aaron Owner',
    ]));

    $member = User::factory()->forOrganization($this->organization, 'Member')->create([
        'name' => 'Beth Member',
    ]);

    $this->membership = $this->organization->memberships()
        ->where('user_id', $member->id)
        ->sole();
});

it('resolves no on escape, no on cancel, and yes only on the confirm control', function (): void {
    $exists = fn (): bool => OrganizationMembership::query()
        ->whereKey($this->membership->id)
        ->exists();

    $page = visit('/settings/members')->wait(1);

    // Ticked once: dismissing the question leaves the selection alone, so the
    // same row can be asked about again.
    $page->click(sprintf('[data-test="select-%s"]', $this->membership->id));

    $ask = fn () => $page->select('[data-test="bulk-action"]', 'remove')
        ->click('[data-test="bulk-apply"]')
        ->assertPresent('[data-test="confirm-dialog"]');

    // Escape is an answer, and the answer is no.
    $ask();
    $page->keys('[data-test="confirm-dialog"]', 'Escape')->assertMissing('[data-test="confirm-dialog"]');

    expect($exists())->toBeTrue();

    // So is the cancel control.
    $ask();
    $page->click('[data-test="confirm-cancel"]')
        ->assertMissing('[data-test="confirm-dialog"]');

    expect($exists())->toBeTrue();

    // Only this one means yes.
    $ask();
    $page->click('[data-test="confirm-proceed"]')
        ->waitForText('Aaron Owner')
        ->assertDontSeeIn('[data-test="table-body"]', 'Beth Member')
        ->assertNoJavaScriptErrors();

    expect($exists())->toBeFalse();
});

/**
 * How dangerous the answer is belongs to the request, not to the caller's choice
 * of colour: a destructive question renders the destructive control without the
 * caller naming a variant.
 */
it('renders a destructive question with the destructive control', function (): void {
    $page = visit('/settings/members')->wait(1);

    $page->click(sprintf('[data-test="select-%s"]', $this->membership->id))
        ->select('[data-test="bulk-action"]', 'remove')
        ->click('[data-test="bulk-apply"]')
        ->assertPresent('[data-test="confirm-dialog"]')
        ->assertSee('This cannot be undone. Nothing has happened yet.');

    expect($page->script(
        "document.querySelector('[data-test=\"confirm-proceed\"]').className",
    ))->toContain('destructive');
});
