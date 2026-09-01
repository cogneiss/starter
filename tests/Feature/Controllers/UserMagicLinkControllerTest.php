<?php

declare(strict_types=1);

use App\Models\User;
use App\Notifications\UserMagicLink;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Notification;

it('renders magic link page', function (): void {
    $response = $this->fromRoute('home')
        ->get(route('magic-link.create'));

    $response->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('user-magic-link/create')
            ->has('status'));
});

it('may send a magic link', function (): void {
    Notification::fake();

    $user = User::factory()->create([
        'email' => 'test@example.com',
    ]);

    $response = $this->fromRoute('magic-link.create')
        ->post(route('magic-link.store'), [
            '_friction' => frictionToken(),
            'email' => 'test@example.com',
        ]);

    $response->assertRedirectToRoute('magic-link.create')
        ->assertSessionHas('status', 'A login link will be sent if the account exists.');

    Notification::assertSentTo($user, UserMagicLink::class);
});

it('returns generic message for non-existent email', function (): void {
    Notification::fake();

    $response = $this->fromRoute('magic-link.create')
        ->post(route('magic-link.store'), [
            '_friction' => frictionToken(),
            'email' => 'nonexistent@example.com',
        ]);

    $response->assertRedirectToRoute('magic-link.create')
        ->assertSessionHas('status', 'A login link will be sent if the account exists.');

    Notification::assertNothingSent();
});

it('requires a valid email', function (): void {
    $response = $this->fromRoute('magic-link.create')
        ->post(route('magic-link.store'), [
            '_friction' => frictionToken(),
            'email' => 'not-an-email',
        ]);

    $response->assertRedirectToRoute('magic-link.create')
        ->assertSessionHasErrors('email');
});

it('may create a session from a magic link', function (): void {
    $user = User::factory()->unverified()->withoutTwoFactor()->create();

    Cache::put('magic-link:token', $user->id, now()->addMinutes(15));

    $response = $this->get(route('magic-link.update', ['token' => 'token']));

    $response->assertRedirectToRoute('dashboard');

    $this->assertAuthenticatedAs($user);

    expect($user->refresh()->email_verified_at)->not->toBeNull();
});

it('leaves an already verified email untouched', function (): void {
    $user = User::factory()->withoutTwoFactor()->create();

    Cache::put('magic-link:token', $user->id, now()->addMinutes(15));

    $this->get(route('magic-link.update', ['token' => 'token']))
        ->assertRedirectToRoute('dashboard');

    expect($user->refresh()->email_verified_at?->toDateTimeString())
        ->toBe($user->created_at->toDateTimeString());
});

it('rejects a token that was already used', function (): void {
    $user = User::factory()->withoutTwoFactor()->create();

    Cache::put('magic-link:token', $user->id, now()->addMinutes(15));

    $this->get(route('magic-link.update', ['token' => 'token']));

    $this->post(route('logout'));

    $response = $this->get(route('magic-link.update', ['token' => 'token']));

    $response->assertRedirectToRoute('magic-link.create')
        ->assertSessionHasErrors('token');

    $this->assertGuest();
});

it('rejects an unknown token', function (): void {
    $response = $this->get(route('magic-link.update', ['token' => 'missing']));

    $response->assertRedirectToRoute('magic-link.create')
        ->assertSessionHasErrors('token');

    $this->assertGuest();
});

it('redirects to two-factor challenge when enabled', function (): void {
    $user = User::factory()->create();

    Cache::put('magic-link:token', $user->id, now()->addMinutes(15));

    $response = $this->get(route('magic-link.update', ['token' => 'token']));

    $response->assertRedirectToRoute('two-factor.login')
        ->assertSessionHas('login.id', $user->id);

    $this->assertGuest();
});

it('throttles magic link requests', function (): void {
    Notification::fake();

    foreach (range(1, 6) as $ignored) {
        $this->post(route('magic-link.store'), ['email' => 'test@example.com'])
            ->assertRedirect();
    }

    $this->post(route('magic-link.store'), ['email' => 'test@example.com'])
        ->assertStatus(429);
});
