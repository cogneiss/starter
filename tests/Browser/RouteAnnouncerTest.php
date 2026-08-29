<?php

declare(strict_types=1);

use App\Models\Organization;
use App\Models\User;

/**
 * A single-page navigation replaces the page without replacing the document, so
 * the browser announces nothing and a screen reader is left reading a page that
 * quietly became a different one. The announcer says which page arrived, and it
 * has to keep saying it — a region that carries the first title and then never
 * changes is the same silence with a nicer first impression.
 */
it('announces the page each inertia visit lands on', function (): void {
    $this->actingAs(User::factory()->forOrganization(Organization::factory()->create())->create());

    visit('/settings/profile')
        ->wait(1)
        ->assertPresent('[data-test="route-announcer"]')
        ->assertSeeIn('[data-test="route-announcer"]', 'Profile settings')
        ->click('Password')
        ->waitForText('Update password')
        ->wait(1)
        ->assertSeeIn('[data-test="route-announcer"]', 'Password settings')
        ->assertDontSeeIn('[data-test="route-announcer"]', 'Profile settings')
        ->assertNoJavaScriptErrors();
});
