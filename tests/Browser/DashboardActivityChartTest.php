<?php

declare(strict_types=1);

use App\Models\ApiRequestLog;
use App\Models\Organization;
use App\Models\User;

/**
 * The deferred chart replaces its skeleton once the data answers, offers a text
 * alternative to a screen reader, and draws without a csp violation.
 */
it('DashboardActivity draws the deferred chart with a text summary', function (): void {
    $organization = Organization::factory()->create();

    $this->actingAs(User::factory()->forOrganization($organization)->create());

    ApiRequestLog::factory()->count(3)->create([
        'organization_id' => $organization->id,
        'created_at' => now()->subDay(),
    ]);

    visit('/dashboard')
        ->waitForText('3 requests in the last 30 days')
        ->assertMissing('[data-test="activity-chart-skeleton"]')
        ->assertPresent('[data-test="activity-chart"] .recharts-surface')
        ->assertNoAccessibilityIssues(level: 3)
        ->assertNoConsoleLogs()
        ->assertNoJavaScriptErrors();
});
