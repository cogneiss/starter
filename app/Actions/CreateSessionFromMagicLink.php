<?php

declare(strict_types=1);

namespace App\Actions;

use App\Models\User;
use Illuminate\Support\Facades\Cache;

final readonly class CreateSessionFromMagicLink
{
    /**
     * Pulling the token is atomic, which makes every link single use.
     */
    public function handle(string $token): ?User
    {
        $id = Cache::pull('magic-link:'.$token);

        if (! is_string($id)) {
            return null;
        }

        return User::query()->find($id);
    }
}
