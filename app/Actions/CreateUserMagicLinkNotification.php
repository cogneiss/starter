<?php

declare(strict_types=1);

namespace App\Actions;

use App\Models\User;
use App\Notifications\UserMagicLink;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Str;

/**
 * Tokens live in the cache, so they only survive as long as the configured
 * store does. Keep `CACHE_STORE` on a shared, persistent driver in production.
 */
final readonly class CreateUserMagicLinkNotification
{
    public function handle(string $email): void
    {
        $user = User::query()->where('email', $email)->first();

        if (! $user instanceof User) {
            return;
        }

        $token = Str::random(40);

        Cache::put(
            'magic-link:'.$token,
            $user->getKey(),
            now()->addMinutes(Config::integer('auth.magic_link.expire')),
        );

        $user->notify(new UserMagicLink($token));
    }
}
