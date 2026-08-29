<?php

declare(strict_types=1);

use App\Models\Organization;
use App\Models\User;

beforeEach(function (): void {
    $organization = Organization::factory()->create();

    $this->actingAs(User::factory()->forOrganization($organization)->create());
});

it('says what is missing when a list matches nothing', function (): void {
    visit('/settings/members?q=Threadbare+Nonsense')
        ->wait(1)
        ->assertPresent('[data-test="empty-state"]')
        ->assertSee('No members match')
        ->assertNoJavaScriptErrors();
});

/**
 * A screen nobody wrote copy for still says something that names the thing it is
 * missing. This is the default path, and it is the one a resource added after
 * this file was written will take.
 */
it('names the resource when a screen has no copy of its own', function (): void {
    visit('/settings/invitations')
        ->wait(1)
        ->assertPresent('[data-test="empty-state"]')
        ->assertSee('No organization invitations to show.')
        ->assertNoJavaScriptErrors();
});

it('shows the same empty state in the command palette', function (): void {
    visit('/dashboard')
        ->wait(1)
        ->keys('html > body', 'Meta+k')
        ->type('[data-test="palette-input"]', 'Threadbare Nonsense')
        ->waitForText('Nothing matches that search')
        ->assertPresent('[data-test="empty-state"]')
        ->assertNoJavaScriptErrors();
});
