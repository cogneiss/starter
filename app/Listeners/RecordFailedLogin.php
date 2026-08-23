<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Models\LoginHistory;
use App\Models\User;
use Illuminate\Auth\Events\Failed;
use Illuminate\Http\Request;

/**
 * Writes the attempts that did not work. An attempt on an address nobody owns
 * still gets a row, with a null `user_id` — that pattern is the interesting one.
 *
 * Listeners in this directory are discovered by their `handle()` signature, so
 * this one needs no registration.
 */
final readonly class RecordFailedLogin
{
    public function __construct(private Request $request) {}

    public function handle(Failed $event): void
    {
        $user = $event->user;
        $email = $event->credentials['email'] ?? null;

        LoginHistory::query()->create([
            'user_id' => $user instanceof User ? $user->id : null,
            'email' => is_string($email) ? $email : '',
            'ip_address' => $this->request->ip(),
            'user_agent' => $this->request->userAgent(),
            'successful' => false,
            'created_at' => now(),
        ]);
    }
}
