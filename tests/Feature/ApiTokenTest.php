<?php

declare(strict_types=1);

use App\Models\ApiToken;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Support\Str;

it('plaintext appears once in the create response and only in its field', function (): void {
    $organization = Organization::factory()->create();
    $user = User::factory()->forOrganization($organization)->create();

    $this->actingAs($user)
        ->post(route('api-token.store'), [
            'name' => 'Reporting integration',
            'abilities' => ['read:users'],
        ])
        ->assertRedirect(route('api-token.edit'));

    $flash = session('inertia.flash_data');
    $plain = $flash['plainTextToken'] ?? null;

    expect($plain)->toBeString()->toContain('|');

    expect(mb_substr_count(json_encode($flash), $plain))->toBe(1);

    $token = ApiToken::withoutOrganizationScope()->sole();

    expect(json_encode($token->getAttributes()))->not->toContain(Str::after($plain, '|'));
});

it('stored token is hashed', function (): void {
    $organization = Organization::factory()->create();
    $user = User::factory()->forOrganization($organization)->create();

    $this->actingAs($user)->post(route('api-token.store'), [
        'name' => 'Reporting integration',
        'abilities' => ['read:users'],
    ]);

    $plain = session('inertia.flash_data')['plainTextToken'];
    $token = ApiToken::withoutOrganizationScope()->sole();

    expect($token->token)->toBe(hash('sha256', Str::after($plain, '|')));
});

it('list payload never contains the plaintext', function (): void {
    $organization = Organization::factory()->create();
    $user = User::factory()->forOrganization($organization)->create();

    $this->actingAs($user)->post(route('api-token.store'), [
        'name' => 'Reporting integration',
        'abilities' => ['read:users'],
    ]);

    $plain = session('inertia.flash_data')['plainTextToken'];

    // The redirect target consumes the one-shot flash; the list page itself
    // must never carry the plaintext again.
    $this->actingAs($user)->get(route('api-token.edit'));

    $page = $this->actingAs($user)
        ->get(route('api-token.edit'))
        ->assertOk()
        ->inertiaPage();

    $payload = json_encode($page);

    expect(mb_substr_count($payload, $plain))->toBe(0)
        ->and(mb_substr_count($payload, Str::after($plain, '|')))->toBe(0);
});

it('list hides revoked and expired by default', function (): void {
    $organization = Organization::factory()->create();
    $user = User::factory()->forOrganization($organization)->create();

    $active = ApiToken::factory()->create(['organization_id' => $organization->id]);
    ApiToken::factory()->revoked()->create(['organization_id' => $organization->id]);
    ApiToken::factory()->expired()->create(['organization_id' => $organization->id]);

    $this->actingAs($user)
        ->get(route('api-token.edit'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('organization/api-tokens')
            ->count('tokens', 1)
            ->where('tokens.0.id', $active->id));
});

it('creating a token with an unregistered ability is rejected', function (): void {
    $organization = Organization::factory()->create();
    $user = User::factory()->forOrganization($organization)->create();

    $this->actingAs($user)
        ->post(route('api-token.store'), [
            'name' => 'Reporting integration',
            'abilities' => ['write:users'],
        ])
        ->assertSessionHasErrors('abilities.0');

    expect(ApiToken::withoutOrganizationScope()->count())->toBe(0);
});

it('tokens:prune respects retention', function (): void {
    $cutoffDays = config()->integer('api.retention.tokens');

    $oldRevoked = ApiToken::factory()->create(['revoked_at' => now()->subDays($cutoffDays + 1)]);
    $oldExpired = ApiToken::factory()->create(['expires_at' => now()->subDays($cutoffDays + 1)]);
    $freshRevoked = ApiToken::factory()->create(['revoked_at' => now()->subDay()]);
    $active = ApiToken::factory()->create();

    $this->artisan('tokens:prune')->assertSuccessful();

    $this->assertDatabaseMissing('personal_access_tokens', ['id' => $oldRevoked->id]);
    $this->assertDatabaseMissing('personal_access_tokens', ['id' => $oldExpired->id]);
    $this->assertDatabaseHas('personal_access_tokens', ['id' => $freshRevoked->id]);
    $this->assertDatabaseHas('personal_access_tokens', ['id' => $active->id]);
});
