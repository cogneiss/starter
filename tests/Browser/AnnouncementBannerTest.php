<?php

declare(strict_types=1);

use App\Models\Announcement;
use App\Models\Organization;
use App\Models\User;

/**
 * An active announcement renders as an accessible status banner, and
 * dismissing it removes it for good.
 */
it('Announcement banner is accessible and stays dismissed', function (): void {
    $organization = Organization::factory()->create();
    $user = User::factory()->forOrganization($organization)->create();

    Announcement::factory()->create(['title' => 'Scheduled maintenance', 'level' => 'warning']);

    $this->actingAs($user);

    visit('/dashboard')
        ->assertSee('Scheduled maintenance')
        ->assertPresent('[data-test="announcement-banner"][role="status"]')
        ->assertNoAccessibilityIssues(level: 3)
        ->click('[data-test="announcement-dismiss"]')
        ->assertMissing('[data-test="announcement-banner"]')
        ->assertNoConsoleLogs()
        ->assertNoJavaScriptErrors();

    expect($user->fresh()->id)->not->toBeNull();

    visit('/dashboard')->assertDontSee('Scheduled maintenance');
});
