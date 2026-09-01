<?php

declare(strict_types=1);

use App\Models\Organization;
use App\Models\User;

/**
 * A CSP violation surfaces as a console error, so a page that renders, opens
 * its overlays and mounts its Echo hook without a single console line is a
 * page the enforcing policy did not break.
 */
beforeEach(function (): void {
    $organization = Organization::factory()->create();

    $this->actingAs(User::factory()->forOrganization($organization)->create());
});

it('loads the dashboard without a csp violation', function (): void {
    visit('/dashboard')->wait(1)
        ->assertSee('Dashboard')
        ->assertNoConsoleLogs()
        ->assertNoJavaScriptErrors();
});

it('opens the command palette on a list page without a csp violation', function (): void {
    visit('/settings/audit')->wait(1)
        ->assertSee('Audit log')
        ->keys('html > body', 'Meta+k')
        ->assertPresent('[data-test="command-palette"]')
        ->type('[data-test="palette-input"]', 'settings')
        ->wait(1)
        ->assertNoConsoleLogs()
        ->assertNoJavaScriptErrors();
});

it('mounts the live notification channel without a csp violation', function (): void {
    // The notification bell subscribes to the organization channel on mount,
    // so any page under the app layout carries a live Echo connection.
    visit('/settings/members')->wait(1)
        ->assertPresent('[data-test="notification-bell"]')
        ->assertNoConsoleLogs()
        ->assertNoJavaScriptErrors();
});
