<?php

declare(strict_types=1);

use App\Models\ApiToken;
use App\Models\Organization;
use App\Models\User;

it('renders the API token settings page', function (): void {
    $organization = Organization::factory()->create();
    $user = User::factory()->forOrganization($organization)->create();

    ApiToken::factory()->count(2)->create(['organization_id' => $organization->id]);

    $this->actingAs($user)
        ->get(route('api-token.edit'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('organization/api-tokens')
            ->count('tokens', 2)
            ->where('abilities', fn ($abilities) => collect($abilities)->contains('read:users')));
});

it('turns a guest away from the API token settings page', function (): void {
    $this->get(route('api-token.edit'))->assertRedirect(route('login'));
});

it('creates a token for the current organization', function (): void {
    $organization = Organization::factory()->create();
    $user = User::factory()->forOrganization($organization)->create();

    $this->actingAs($user)
        ->post(route('api-token.store'), [
            'name' => 'Reporting integration',
            'abilities' => ['read:users'],
            'expires_at' => now()->addMonth()->toDateString(),
        ])
        ->assertRedirect(route('api-token.edit'));

    $token = ApiToken::withoutOrganizationScope()->sole();

    expect($token->organization_id)->toBe($organization->id)
        ->and($token->created_by)->toBe($user->id)
        ->and($token->name)->toBe('Reporting integration')
        ->and($token->abilities)->toBe(['read:users'])
        ->and($token->expires_at)->not->toBeNull();
});

it('create token ignores an organization supplied in the request', function (): void {
    $organization = Organization::factory()->create();
    $other = Organization::factory()->create();
    $user = User::factory()->forOrganization($organization)->create();

    $payload = ['name' => 'Reporting integration', 'abilities' => ['read:users']];

    // Case 1: organization_id in the body.
    $this->actingAs($user)->post(route('api-token.store'), [
        ...$payload,
        'organization_id' => $other->id,
    ]);

    // Case 2: organization and org in the body.
    $this->actingAs($user)->post(route('api-token.store'), [
        ...$payload,
        'organization' => $other->id,
        'org' => $other->slug,
    ]);

    // Case 3: organization_id and org in the query string.
    $this->actingAs($user)->post(
        route('api-token.store').'?organization_id='.$other->id.'&org='.$other->slug,
        $payload,
    );

    $tokens = ApiToken::withoutOrganizationScope()->get();

    expect($tokens)->toHaveCount(3)
        ->each(fn ($token) => $token->organization_id->toBe($organization->id));
});

it('revokes a token', function (): void {
    $organization = Organization::factory()->create();
    $user = User::factory()->forOrganization($organization)->create();

    $token = ApiToken::factory()->create(['organization_id' => $organization->id]);

    $this->actingAs($user)
        ->delete(route('api-token.destroy', $token))
        ->assertRedirect(route('api-token.edit'));

    expect($token->refresh()->revoked_at)->not->toBeNull();
});

it("revoking another organization's token is a 404", function (): void {
    $organization = Organization::factory()->create();
    $user = User::factory()->forOrganization($organization)->create();

    $foreign = ApiToken::factory()->create();

    $this->actingAs($user)
        ->delete(route('api-token.destroy', $foreign->id))
        ->assertNotFound();

    expect($foreign->refresh()->revoked_at)->toBeNull();
});
