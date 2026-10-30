<?php

declare(strict_types=1);

use App\Models\ApiRequestLog;
use App\Models\Organization;
use App\Models\User;
use Inertia\Testing\AssertableInertia;

/**
 * The dashboard's activity chart arrives as a deferred prop: the first
 * response omits it and the follow-up carries only this organization's days.
 */
it('DashboardActivity defers the chart data and scopes it to the organization', function (): void {
    $organization = Organization::factory()->create();
    $member = User::factory()->forOrganization($organization)->create();

    ApiRequestLog::factory()->count(2)->create([
        'organization_id' => $organization->id,
        'created_at' => now()->subDays(2),
    ]);
    ApiRequestLog::factory()->create([
        'organization_id' => $organization->id,
        'created_at' => now()->subDay(),
    ]);
    ApiRequestLog::factory()->count(5)->create([
        'organization_id' => Organization::factory()->create()->id,
        'created_at' => now()->subDay(),
    ]);

    $this->actingAs($member)
        ->get(route('dashboard'))
        ->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
            ->component('dashboard')
            ->missing('activity')
            ->loadDeferredProps(fn (AssertableInertia $reload): AssertableInertia => $reload
                ->count('activity', 2)
                ->where('activity.0.requests', 2)
                ->where('activity.1.requests', 1)));
});
