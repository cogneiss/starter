<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\EnabledSocialProviders;
use App\Actions\StopImpersonation;
use App\Http\Requests\CreateSessionRequest;
use App\Models\User;
use App\Support\Impersonation;
use Illuminate\Auth\Events\Failed;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use Inertia\Response;

final readonly class SessionController
{
    public function create(Request $request, EnabledSocialProviders $providers): Response
    {
        return Inertia::render('session/create', [
            'canResetPassword' => Route::has('password.request'),
            'canRegister' => Route::has('register'),
            'status' => $request->session()->get('status'),
            'socialProviders' => $providers->handle(),
        ]);
    }

    public function store(CreateSessionRequest $request): RedirectResponse
    {
        $request->ensureIsNotRateLimited();

        $user = $this->authenticate($request);

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

    public function destroy(Request $request, Impersonation $impersonation, StopImpersonation $stop): RedirectResponse
    {
        if ($impersonation->active()) {
            $stop->handle();
        }

        Auth::guard('web')->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/');
    }

    private function authenticate(CreateSessionRequest $request): ?User
    {
        $credentials = $request->only('email', 'password');
        $provider = Auth::getProvider();
        $user = $provider->retrieveByCredentials($credentials);

        if ($user instanceof User && $provider->validateCredentials($user, $credentials)) {
            return $user;
        }

        event(new Failed('web', $user, $credentials));

        return null;
    }
}
