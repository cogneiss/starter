<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Auth\AuthDriverResolver;
use App\Http\Requests\CreateSessionRequest;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Response;

final readonly class SessionController
{
    public function create(Request $request, AuthDriverResolver $drivers): RedirectResponse|Response
    {
        return $drivers->driver()->redirect($request);
    }

    public function store(CreateSessionRequest $request, AuthDriverResolver $drivers): RedirectResponse
    {
        $request->ensureIsNotRateLimited();

        $user = $drivers->driver()->authenticate($request);

        if (! $user instanceof User) {
            $request->failed();
        }

        $request->succeeded();

        if ($user->hasEnabledTwoFactorAuthentication()) {
            $request->session()->put([
                'login.id' => $user->getKey(),
                'login.remember' => $request->boolean('remember'),
            ]);

            return to_route('two-factor.login');
        }

        Auth::login($user, $request->boolean('remember'));

        $request->session()->regenerate();

        return redirect()->intended(route('dashboard', absolute: false));
    }

    public function destroy(Request $request): RedirectResponse
    {
        Auth::guard('web')->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/');
    }
}
