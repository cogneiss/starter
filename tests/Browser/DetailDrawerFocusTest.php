<?php

declare(strict_types=1);

use App\Models\Organization;
use App\Models\User;

/**
 * Opening a drawer moves the keyboard into it, so closing has to move the
 * keyboard back. Anything else drops a person reading down a list at the top of
 * the document with no idea which row they were on.
 */
it('closes on escape and gives the keyboard back to the row that opened it', function (): void {
    $organization = Organization::factory()->create();

    $this->actingAs(User::factory()->forOrganization($organization)->create([
        'name' => 'Aaron Owner',
    ]));

    $member = User::factory()->forOrganization($organization, 'Member')->create([
        'name' => 'Beth Member',
    ]);

    $membership = $organization->memberships()->where('user_id', $member->id)->sole();

    $page = visit('/settings/members')->wait(1);

    $page->click(sprintf('[data-test="peek-%s"]', $membership->id))
        ->waitForText('Beth Member')
        ->assertPresent('[data-test="detail-drawer"]')
        ->keys('[data-test="detail-drawer"]', 'Escape')
        ->assertMissing('[data-test="detail-drawer"]')
        ->wait(1)
        ->assertNoJavaScriptErrors();

    expect($page->script(
        'document.activeElement.getAttribute("data-test")',
    ))->toBe(sprintf('peek-%s', $membership->id));
});
