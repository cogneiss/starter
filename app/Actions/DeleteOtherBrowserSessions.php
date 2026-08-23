<?php

declare(strict_types=1);

namespace App\Actions;

use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Signs every other browser out. The password is re-checked by the guard,
 * which rotates the session password hash so the other sessions die on their
 * next request; the rows are then removed so the list matches reality.
 */
final readonly class DeleteOtherBrowserSessions
{
    public function handle(User $user, string $password, string $currentSessionId): void
    {
        Auth::guard('web')->logoutOtherDevices($password);

        if (config()->string('session.driver') !== 'database') {
            return;
        }

        DB::table(config()->string('session.table', 'sessions'))
            ->where('user_id', $user->id)
            ->where('id', '!=', $currentSessionId)
            ->delete();
    }
}
