<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\CreateSessionFromMagicLink;
use App\Actions\CreateUserMagicLinkNotification;
use App\Http\Requests\CreateUserMagicLinkRequest;
use App\Models\User;
use App\Support\FormFriction;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

final readonly class UserMagicLinkController
{
    public function create(Request $request, FormFriction $friction): Response
    {
        return Inertia::render('user-magic-link/create', [
            'status' => $request->session()->get('status'),
            'friction' => $friction->props(),
        ]);
    }

    public function store(
        CreateUserMagicLinkRequest $request,
        CreateUserMagicLinkNotification $action
    ): RedirectResponse {
        $action->handle($request->string('email')->value());

        return back()->with('status', __('A login link will be sent if the account exists.'));
    }

    public function update(
        Request $request,
        string $token,
        CreateSessionFromMagicLink $action
    ): RedirectResponse {
        $user = $action->handle($token);

        if (! $user instanceof User) {
            return to_route('magic-link.create')
                ->withErrors(['token' => __('This login link is invalid or has expired.')]);
        }

        if (! $user->hasVerifiedEmail()) {
            $user->markEmailAsVerified();
        }

        if ($user->hasEnabledTwoFactorAuthentication()) {
            $request->session()->put([
                'login.id' => $user->getKey(),
                'login.remember' => false,
            ]);

            return to_route('two-factor.login');
        }

        Auth::login($user);

        $request->session()->regenerate();

        return redirect()->intended(route('dashboard', absolute: false));
    }
}
