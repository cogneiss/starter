<?php

declare(strict_types=1);

use App\Models\ApiToken;
use App\Models\Organization;
use App\Models\User;

/**
 * A canary for `.work/checks-a.sh secrets`: a token with a known plaintext is
 * exercised against the API so the secrets check can grep the captured test-run
 * output and storage/logs for the plaintext and prove nothing logged it.
 */
it('a token with a known plaintext round-trips without appearing anywhere', function (): void {
    $organization = Organization::factory()->create();
    User::factory()->forOrganization($organization)->create();

    $plain = 'canary-plaintext-8c41d27b19e6';

    $token = ApiToken::factory()->create([
        'organization_id' => $organization->id,
        'tokenable_id' => User::factory()->forOrganization($organization)->create()->id,
        'token' => hash('sha256', $plain),
        'abilities' => ['read:users'],
    ]);

    $this->withHeader('Authorization', 'Bearer '.$token->id.'|'.$plain)
        ->get('/api/v1/users')
        ->assertOk();
});
