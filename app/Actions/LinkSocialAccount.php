<?php

declare(strict_types=1);

namespace App\Actions;

use App\Models\SocialAccount;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Laravel\Socialite\Contracts\User as SocialiteUser;

final readonly class LinkSocialAccount
{
    public function __construct(private CreateUser $users) {}

    /**
     * Resolve the user behind an OAuth identity, linking or creating as needed.
     *
     * An unverified local account is never auto-linked: anybody may sign up
     * with somebody else's address, so linking on an unclaimed email hands the
     * account to whoever holds it at the provider.
     *
     * @throws AuthorizationException
     */
    public function handle(string $provider, SocialiteUser $identity): User
    {
        $email = (string) $identity->getEmail();
        $providerUserId = (string) $identity->getId();

        $account = SocialAccount::query()
            ->where('provider', $provider)
            ->where('provider_user_id', $providerUserId)
            ->first();

        if ($account instanceof SocialAccount) {
            return $account->user;
        }

        $user = User::query()->where('email', $email)->first();

        throw_if($user instanceof User && ! $user->hasVerifiedEmail(), AuthorizationException::class);

        return DB::transaction(function () use ($provider, $providerUserId, $identity, $email, $user): User {
            $user ??= $this->users->handle([
                'name' => (string) ($identity->getName() ?? $email),
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
}
