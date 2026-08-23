<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Data\PasskeyData;
use App\Models\User;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Inertia\Inertia;
use Inertia\Response;
use Laravel\Fortify\Features;
use Laravel\Passkeys\Passkey;

final readonly class UserPasskeyController implements HasMiddleware
{
    public static function middleware(): array
    {
        return Features::optionEnabled(Features::passkeys(), 'confirmPassword')
            ? [new Middleware('password.confirm', only: ['show'])]
            : [];
    }

    public function show(#[CurrentUser] User $user): Response
    {
        $canManagePasskeys = Features::canManagePasskeys();

        return Inertia::render('user-passkey/show', [
            'canManagePasskeys' => $canManagePasskeys,
            'passkeys' => $canManagePasskeys ? $this->passkeys($user) : [],
        ]);
    }

    /**
     * @return array<int, PasskeyData>
     */
    private function passkeys(User $user): array
    {
        return $user->passkeys()
            ->select(['id', 'name', 'credential', 'created_at', 'last_used_at'])
            ->latest()
            ->get()
            ->map(fn (Passkey $passkey): PasskeyData => PasskeyData::fromModel($passkey))
            ->values()
            ->all();
    }
}
