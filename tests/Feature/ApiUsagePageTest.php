<?php

declare(strict_types=1);

use App\Models\ApiRequestLog;
use App\Models\Organization;
use App\Models\User;
use Inertia\Testing\AssertableInertia;
use Laravel\Pennant\Feature;

/**
 * The tenant-facing usage page: an organization's own traffic, and never
 * another's.
 */
beforeEach(function (): void {
    $this->organization = Organization::factory()->create();
    $this->member = User::factory()->forOrganization($this->organization)->create();
});

it('renders requests over time, endpoints, top tokens and the current limit', function (): void {
    [$token] = apiBearer($this->organization, user: $this->member);

    ApiRequestLog::factory()->count(2)->create([
        'organization_id' => $this->organization->id,
        'api_token_id' => $token->id,
        'created_at' => now()->subDays(2),
    ]);
    ApiRequestLog::factory()->create([
        'organization_id' => $this->organization->id,
        'api_token_id' => null,
        'path' => 'api/v1',
        'resource' => null,
        'status' => 429,
        'created_at' => now()->subDay(),
    ]);

    $this->actingAs($this->member)
        ->get('/settings/organization/api-usage')
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
            ->component('organization/api-usage')
            ->where('usage.requests', 3)
            ->where('usage.throttled', 1)
            ->where('usage.tier', 'standard')
            ->where('usage.limit', config()->integer('api.rate_tiers.tiers.standard.per_organization'))
            ->where('usage.remaining', config()->integer('api.rate_tiers.tiers.standard.per_organization'))
            ->count('usage.daily', 2)
            ->count('usage.endpoints', 2)
            ->count('usage.tokens', 2)
            ->where('usage.endpoints.0.name', 'api/v1/users')
            ->where('usage.endpoints.0.requests', 2)
            ->where('usage.tokens.0.name', $token->name)
            ->where('usage.tokens.1.name', 'deleted token'),
        );
});

it('reports an unknown tier under the default', function (): void {
    Feature::for($this->organization)->activate('api-rate-tier', 'no-such-tier');

    $this->actingAs($this->member)
        ->get('/settings/organization/api-usage')
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
            ->where('usage.tier', 'standard')
            ->where('usage.requests', 0),
        );
});

it('never shows another organization its numbers', function (): void {
    $other = Organization::factory()->create();

    ApiRequestLog::factory()->count(5)->create([
        'organization_id' => $other->id,
        'path' => 'api/v1/organizations',
        'resource' => 'organizations',
    ]);

    $this->actingAs($this->member)
        ->get('/settings/organization/api-usage')
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
            ->component('organization/api-usage')
            ->where('usage.requests', 0)
            ->count('usage.endpoints', 0)
            ->count('usage.tokens', 0),
        );
});
