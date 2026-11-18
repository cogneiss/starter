<?php

declare(strict_types=1);

use App\Models\SocialAccount;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\User as SocialiteUser;

beforeEach(function (): void {
    config()->set('features.defaults.social-login-enabled', true);
    config()->set('services.github.client_id', 'client-id');
});

function socialiteIdentity(string $id = 'provider-1', string $email = 'ada@example.com'): SocialiteUser
{
    $identity = new SocialiteUser;
    $identity->id = $id;
    $identity->email = $email;
    $identity->name = 'Ada Lovelace';

    return $identity;
}

function fakeSocialite(?SocialiteUser $identity = null, string $driver = 'github'): void
{
    $provider = Mockery::mock();
    $provider->shouldReceive('redirect')
        ->andReturn(new RedirectResponse('https://github.com/login/oauth/authorize'));
    $provider->shouldReceive('user')->andReturn($identity ?? socialiteIdentity());

    Socialite::shouldReceive('driver')->with($driver)->andReturn($provider);
}

it('redirects to the provider', function (): void {
    fakeSocialite();

    $this->get(route('social-auth.show', 'github'))
        ->assertRedirect('https://github.com/login/oauth/authorize');
});

it('signs a new user in from the provider callback', function (): void {
    fakeSocialite();

    $this->get(route('social-auth.update', 'github'))
        ->assertRedirect(route('dashboard', absolute: false));

    $user = User::query()->sole();

    $this->assertAuthenticatedAs($user);

    expect($user->hasVerifiedEmail())->toBeTrue()
        ->and(SocialAccount::query()->count())->toBe(1);
});

it('sends a user with two-factor enabled to the challenge instead of signing them in', function (): void {
    $user = User::factory()->create([
        'email' => 'ada@example.com',
        'two_factor_secret' => encrypt('secret'),
        'two_factor_recovery_codes' => encrypt(json_encode(['code1', 'code2'])),
        'two_factor_confirmed_at' => now(),
    ]);

    fakeSocialite();

    $this->get(route('social-auth.update', 'github'))
        ->assertRedirectToRoute('two-factor.login')
        ->assertSessionHas('login.id', $user->getKey());

    $this->assertGuest();
});

it('signs a user without two-factor straight in', function (): void {
    $user = User::factory()->withoutTwoFactor()->create(['email' => 'ada@example.com']);

    fakeSocialite();

    $this->get(route('social-auth.update', 'github'))
        ->assertRedirect(route('dashboard', absolute: false))
        ->assertSessionMissing('login.id');

    $this->assertAuthenticatedAs($user);
});

it('refuses the callback when the local account is unverified', function (): void {
    User::factory()->unverified()->create(['email' => 'ada@example.com']);

    fakeSocialite();

    $this->get(route('social-auth.update', 'github'))->assertForbidden();

    $this->assertGuest();
});

it('signs a new user in when google vouches for the provider email', function (): void {
    config()->set('services.google.client_id', 'client-id');
    fakeSocialite(socialiteIdentity()->setRaw(['email_verified' => true]), 'google');

    $this->get(route('social-auth.update', 'google'))
        ->assertRedirect(route('dashboard', absolute: false));

    $this->assertAuthenticatedAs(User::query()->sole());
});

it('refuses an unverified provider email', function (): void {
    config()->set('services.google.client_id', 'client-id');
    fakeSocialite(socialiteIdentity()->setRaw(['email_verified' => false]), 'google');

    $this->get(route('social-auth.update', 'google'))->assertForbidden();

    $this->assertGuest();

    expect(User::query()->exists())->toBeFalse();
});

it('refuses an empty provider email', function (): void {
    fakeSocialite(socialiteIdentity(email: ''));

    $this->get(route('social-auth.update', 'github'))->assertForbidden();

    $this->assertGuest();

    expect(User::query()->exists())->toBeFalse();
});

it('hides a provider that has no credentials configured', function (): void {
    config()->set('services.github.client_id', '');

    $this->get(route('social-auth.show', 'github'))->assertNotFound();
});

it('hides every provider while the feature is off', function (): void {
    config()->set('features.defaults.social-login-enabled', false);

    $this->get(route('social-auth.show', 'github'))->assertNotFound();
});

it('hides a provider this application does not know', function (): void {
    $this->get(route('social-auth.show', 'gitlab'))->assertNotFound();
});

it('offers the configured providers on the login page', function (): void {
    $this->get(route('login'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->where('socialProviders', ['github']));
});
