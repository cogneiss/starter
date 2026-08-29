<?php

declare(strict_types=1);

use App\Models\Organization;
use App\Models\User;
use App\Support\OrganizationContext;

/**
 * Inline edits are keyed by record, not held as one flag for the table.
 *
 * The requests are slowed down here so both are still in flight at the same
 * moment, which is the only moment the distinction is visible: each row shows
 * its own spinner and holds its own value, and neither row ever displays the
 * value the other row is sending.
 */
it('keeps two concurrent inline edits apart', function (): void {
    $organization = Organization::factory()->create();

    $this->actingAs(User::factory()->forOrganization($organization)->create([
        'name' => 'Aaron Owner',
    ]));

    $beth = User::factory()->forOrganization($organization, 'Member')->create([
        'name' => 'Beth Member',
    ]);

    $cara = User::factory()->forOrganization($organization, 'Member')->create([
        'name' => 'Cara Member',
    ]);

    $bethRow = $organization->memberships()->where('user_id', $beth->id)->sole();
    $caraRow = $organization->memberships()->where('user_id', $cara->id)->sole();

    $page = visit('/settings/members')->wait(1);

    // Hold every request open long enough for both edits to overlap. The
    // patch is installed through `window.eval` because a script evaluated by
    // the driver runs in its own world, and a native method captured there
    // refuses to be called on the page's own objects later.
    $page->script(<<<'JS'
        window.eval(`(function () {
            const send = XMLHttpRequest.prototype.send;

            XMLHttpRequest.prototype.send = function (body) {
                const request = this;

                setTimeout(function () {
                    send.call(request, body);
                }, 1500);
            };
        })()`);
    JS);

    $page->select(sprintf('[data-test="role-%s"]', $bethRow->id), 'Admin')
        ->select(sprintf('[data-test="role-%s"]', $caraRow->id), 'Owner')
        ->assertPresent(sprintf('[data-test="patching-%s"]', $bethRow->id))
        ->assertPresent(sprintf('[data-test="patching-%s"]', $caraRow->id))
        ->assertValue(sprintf('[data-test="role-%s"]', $bethRow->id), 'Admin')
        ->assertValue(sprintf('[data-test="role-%s"]', $caraRow->id), 'Owner')
        ->wait(4)
        ->assertMissing(sprintf('[data-test="patching-%s"]', $bethRow->id))
        ->assertMissing(sprintf('[data-test="patching-%s"]', $caraRow->id))
        ->assertValue(sprintf('[data-test="role-%s"]', $bethRow->id), 'Admin')
        ->assertValue(sprintf('[data-test="role-%s"]', $caraRow->id), 'Owner')
        ->assertNoJavaScriptErrors();

    resolve(OrganizationContext::class)->runAs($organization, function () use ($beth, $cara): void {
        expect($beth->fresh()?->hasRole('Admin'))->toBeTrue()
            ->and($cara->fresh()?->hasRole('Owner'))->toBeTrue();
    });
});
