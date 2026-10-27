<?php

declare(strict_types=1);

use App\Models\User;

it('renders passkeys page', function (): void {
    $user = User::factory()->create();

    $this->actingAs($user)->session(['auth.password_confirmed_at' => time()]);

    $response = $this->fromRoute('dashboard')
        ->get(route('passkey.show'));

    $response->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('user-passkey/show')
            ->where('canManagePasskeys', true)
            ->where('passkeys', []));
});

it('lists the users passkeys', function (): void {
    $user = User::factory()->create();

    $user->passkeys()->create([
        'name' => 'MacBook Pro',
        'credential_id' => 'credential-id-1',
        'credential' => ['aaguid' => 'unknown'],
    ]);

    $this->actingAs($user)->session(['auth.password_confirmed_at' => time()]);

    $response = $this->fromRoute('dashboard')
        ->get(route('passkey.show'));

    $response->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('user-passkey/show')
            ->has('passkeys', 1)
            ->where('passkeys.0.name', 'MacBook Pro'));
});

it('returns no passkeys when the feature is disabled', function (): void {
    config()->set('fortify.features', []);

    $user = User::factory()->create();

    $user->passkeys()->create([
        'name' => 'MacBook Pro',
        'credential_id' => 'credential-id-1',
        'credential' => ['aaguid' => 'unknown'],
    ]);

    $this->actingAs($user)->session(['auth.password_confirmed_at' => time()]);

    $response = $this->fromRoute('dashboard')
        ->get(route('passkey.show'));

    $response->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('user-passkey/show')
            ->where('canManagePasskeys', false)
            ->where('passkeys', []));
});

it('requires password confirmation to view passkeys', function (): void {
    $user = User::factory()->create();

    $response = $this->actingAs($user)
        ->fromRoute('dashboard')
        ->get(route('passkey.show'));

    $response->assertRedirect(route('password.confirm'));
});

it('does not allow guests to view passkeys', function (): void {
    $response = $this->get(route('passkey.show'));

    $response->assertRedirectToRoute('login');
});

it('throttles passkey sign-in attempts for one credential', function (): void {
    $attempt = ['credential' => ['id' => 'credential-id-1']];

    foreach (range(1, 10) as $ignored) {
        expect($this->post(route('passkey.login'), $attempt)->status())->not->toBe(429);
    }

    $this->post(route('passkey.login'), $attempt)->assertTooManyRequests();
});

it('requires a verified email to view passkeys', function (): void {
    $user = User::factory()->unverified()->withoutTwoFactor()->create();

    $response = $this->actingAs($user)
        ->get(route('passkey.show'));

    $response->assertRedirectToRoute('verification.notice');
});

it('exposes passkey endpoints', function (): void {
    $response = $this->get(route('well-known.passkeys'));

    $response->assertOk()
        ->assertExactJson([
            'enroll' => route('passkey.show'),
            'manage' => route('passkey.show'),
        ]);
});
