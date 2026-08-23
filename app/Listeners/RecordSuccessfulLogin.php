<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Models\LoginHistory;
use App\Models\User;
use Illuminate\Auth\Events\Login;
use Illuminate\Http\Request;

/**
 * Writes the sign-in a user can recognise on their account security page.
 *
 * Listeners in this directory are discovered by their `handle()` signature, so
 * this one needs no registration.
 */
final readonly class RecordSuccessfulLogin
{
    public function __construct(private Request $request) {}

    public function handle(Login $event): void
    {
        $user = $event->user;

        if (! $user instanceof User) {
            return;
        }

        LoginHistory::query()->create([
            'user_id' => $user->id,
            'email' => $user->email,
            'ip_address' => $this->request->ip(),
            'user_agent' => $this->request->userAgent(),
            'successful' => true,
            'created_at' => now(),
        ]);
    }
}
