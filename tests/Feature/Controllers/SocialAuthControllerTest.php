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

function fakeSocialite(?SocialiteUser $identity = null): void
{
    $provider = Mockery::mock();
    $provider->shouldReceive('redirect')
        ->andReturn(new RedirectResponse('https://github.com/login/oauth/authorize'));
    $provider->shouldReceive('user')->andReturn($identity ?? socialiteIdentity());

    Socialite::shouldReceive('driver')->with('github')->andReturn($provider);
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

it('refuses the callback when the local account is unverified', function (): void {
    User::factory()->unverified()->create(['email' => 'ada@example.com']);

    fakeSocialite();

    $this->get(route('social-auth.update', 'github'))->assertForbidden();

    $this->assertGuest();
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
