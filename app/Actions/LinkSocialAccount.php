<?php

declare(strict_types=1);

namespace App\Actions;

use App\Models\SocialAccount;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Laravel\Socialite\AbstractUser;
use Laravel\Socialite\Contracts\User as SocialiteUser;

final readonly class LinkSocialAccount
{
    public function __construct(private CreateUser $users) {}

    /**
     * Resolve the user behind an OAuth identity, linking or creating as needed.
     *
     * An unverified local account is never auto-linked: anybody may sign up
     * with somebody else's address, so linking on an unclaimed email hands the
     * account to whoever holds it at the provider. A new identity whose email
     * is empty or that the provider does not vouch for is refused too.
     *
     * @throws AuthorizationException
     */
    public function handle(string $provider, SocialiteUser $identity): User
    {
        $email = (string) $identity->getEmail();
        $providerUserId = $identity->getId();

        $account = SocialAccount::query()
            ->where('provider', $provider)
            ->where('provider_user_id', $providerUserId)
            ->first();

        if ($account instanceof SocialAccount) {
            return $account->user;
        }

        throw_unless($email !== '' && $this->vouchesFor($provider, $identity), AuthorizationException::class);

        $user = User::query()->where('email', $email)->first();

        throw_if($user instanceof User && ! $user->hasVerifiedEmail(), AuthorizationException::class);

        return DB::transaction(function () use ($provider, $providerUserId, $identity, $email, $user): User {
            $user ??= $this->users->handle([
                'name' => $identity->getName() ?? $email,
                'email' => $email,
                'email_verified_at' => now(),
            ], Str::password(32));

            SocialAccount::query()->create([
                'user_id' => $user->id,
                'provider' => $provider,
                'provider_user_id' => $providerUserId,
                'created_at' => now(),
            ]);

            return $user;
        });
    }

    /**
     * Whether the provider itself verified the address, since linking and
     * creating both treat it as verified. GitHub's driver returns only a
     * verified primary address under its default user:email scope, Google says
     * so in email_verified, and any other provider is refused.
     */
    private function vouchesFor(string $provider, SocialiteUser $identity): bool
    {
        $raw = $identity instanceof AbstractUser ? $identity->getRaw() : [];

        return match ($provider) {
            'github' => true,
            'google' => ($raw['email_verified'] ?? false) === true,
            default => false,
        };
    }
}
