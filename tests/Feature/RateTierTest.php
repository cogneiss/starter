<?php

declare(strict_types=1);

use App\Models\ApiRequestLog;
use App\Models\Organization;
use Illuminate\Contracts\Auth\Factory;
use Laravel\Pennant\Feature;

/**
 * Per-plan rate tiers: the tier name comes from Pennant, the numbers from
 * config, and the stricter of the per-token and per-organization windows wins.
 * Caps are shrunk here so a test exhausts a window in a handful of requests.
 */
beforeEach(function (): void {
    config()->set('api.rate_tiers.tiers', [
        'standard' => ['per_token' => 2, 'per_organization' => 3],
        'pro' => ['per_token' => 10, 'per_organization' => 20],
    ]);

    $this->organization = Organization::factory()->create();

    [$this->token, $this->bearer] = apiBearer($this->organization);
});

it('exceeding the tier returns 429 and is logged', function (): void {
    $this->withHeader('Authorization', $this->bearer)->get('/api/v1/users')->assertOk();
    $this->withHeader('Authorization', $this->bearer)->get('/api/v1/users')->assertOk();

    $response = $this->withHeader('Authorization', $this->bearer)->get('/api/v1/users');

    $response->assertStatus(429)
        ->assertHeader('Retry-After')
        ->assertHeader('Content-Type', 'application/json');

    expect((int) $response->headers->get('Retry-After'))->toBeGreaterThan(0)
        ->and(ApiRequestLog::withoutOrganizationScope()->where('status', 429)->count())->toBe(1);
});

it('higher tier is not limited at the lower threshold', function (): void {
    Feature::for($this->organization)->activate('api-rate-tier', 'pro');

    foreach (range(1, 5) as $i) {
        $this->withHeader('Authorization', $this->bearer)->get('/api/v1/users')->assertOk();
    }
});

it('an unknown tier name falls back to the default tier', function (): void {
    Feature::for($this->organization)->activate('api-rate-tier', 'no-such-tier');

    $this->withHeader('Authorization', $this->bearer)->get('/api/v1/users')
        ->assertOk()
        ->assertHeader('X-RateLimit-Limit', '2');
});

it('stricter of token and org limit wins', function (): void {
    [, $second] = apiBearer($this->organization);

    // Token cap (2) below org cap (3): the same token's third request is
    // refused even though the organization window still has room.
    $this->withHeader('Authorization', $this->bearer)->get('/api/v1/users')->assertOk();
    $this->withHeader('Authorization', $this->bearer)->get('/api/v1/users')->assertOk();
    $this->withHeader('Authorization', $this->bearer)->get('/api/v1/users')->assertStatus(429);

    // Two tokens jointly exhausting the org cap: the second token has used only
    // one of its own two, but the organization's three are spent. The guard is
    // flushed so the second request authenticates as its own token.
    resolve(Factory::class)->forgetGuards();

    $this->withHeader('Authorization', $second)->get('/api/v1/users')->assertOk();
    $this->withHeader('Authorization', $second)->get('/api/v1/users')->assertStatus(429);
});

it('rate limit headers are present', function (): void {
    $response = $this->withHeader('Authorization', $this->bearer)->get('/api/v1/users');

    $response->assertOk()
        ->assertHeader('X-RateLimit-Limit', '2')
        ->assertHeader('X-RateLimit-Remaining', '1');

    expect((int) $response->headers->get('X-RateLimit-Reset'))->toBeGreaterThan(time() - 1);
});
