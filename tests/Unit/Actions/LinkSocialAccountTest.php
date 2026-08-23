<?php

declare(strict_types=1);

use App\Actions\LinkSocialAccount;
use App\Models\SocialAccount;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Laravel\Socialite\Two\User as SocialiteUser;

function identity(string $id = 'provider-1', string $email = 'ada@example.com', ?string $name = 'Ada Lovelace'): SocialiteUser
{
    $identity = new SocialiteUser;
    $identity->id = $id;
    $identity->email = $email;
    $identity->name = $name;

    return $identity;
}

it('creates a verified user and a personal organization for a new identity', function (): void {
    $user = resolve(LinkSocialAccount::class)->handle('github', identity());

    expect($user->email)->toBe('ada@example.com')
        ->and($user->name)->toBe('Ada Lovelace')
        ->and($user->hasVerifiedEmail())->toBeTrue()
        ->and($user->organizations()->count())->toBe(1)
        ->and(SocialAccount::query()->count())->toBe(1);
});

it('falls back to the email when the provider has no name', function (): void {
    $user = resolve(LinkSocialAccount::class)->handle('github', identity(name: null));

    expect($user->name)->toBe('ada@example.com');
});

it('links a provider identity to an existing verified user', function (): void {
    $existing = User::factory()->create(['email' => 'ada@example.com']);

    $user = resolve(LinkSocialAccount::class)->handle('github', identity());

    expect($user->id)->toBe($existing->id)
        ->and(SocialAccount::query()->where('user_id', $existing->id)->count())->toBe(1);
});

it('refuses to link a provider identity to an unverified user', function (): void {
    User::factory()->unverified()->create(['email' => 'ada@example.com']);

    expect(fn () => resolve(LinkSocialAccount::class)->handle('github', identity()))
        ->toThrow(AuthorizationException::class);

    expect(SocialAccount::query()->count())->toBe(0);
});

it('is idempotent for an identity that is already linked', function (): void {
    $existing = User::factory()->create();
    SocialAccount::factory()->create([
        'user_id' => $existing->id,
        'provider' => 'github',
        'provider_user_id' => 'provider-1',
    ]);

    $user = resolve(LinkSocialAccount::class)->handle('github', identity());

    expect($user->id)->toBe($existing->id)
        ->and(SocialAccount::query()->count())->toBe(1);
});
