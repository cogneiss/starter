<?php

declare(strict_types=1);

use App\Models\Organization;
use App\Models\User;

/**
 * The palette does not make an Inertia visit — it calls the endpoint directly —
 * so none of the router's events fire for it. It goes through the same module
 * anyway, which is why a request that fails there says the same thing a failed
 * visit would rather than nothing at all.
 */
it('says the same sentence when a direct request fails', function (): void {
    $organization = Organization::factory()->create();

    $this->actingAs(User::factory()->forOrganization($organization)->create());

    // Longer than the endpoint accepts, so the answer is a refusal rather than
    // a result set.
    visit('/dashboard')
        ->wait(1)
        ->keys('html > body', 'Meta+k')
        ->type('[data-test="palette-input"]', str_repeat('a', 300))
        ->waitForText('Some of what was sent was not valid.')
        // The palette still says its own piece as well: the toast explains the
        // failure, the panel keeps the way back to a working search.
        ->assertPresent('[data-test="palette-retry"]')
        ->assertNoJavaScriptErrors();
});
