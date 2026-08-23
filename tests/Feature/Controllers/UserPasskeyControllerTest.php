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
