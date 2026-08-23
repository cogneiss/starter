<?php

declare(strict_types=1);

namespace App\Auth\Drivers;

use App\Auth\Contracts\AuthDriver;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Email and password, the flow this application shipped with.
 */
final readonly class PasswordAuthDriver implements AuthDriver
{
    public function key(): string
    {
        return 'password';
    }

    public function redirect(Request $request): Response
    {
        return Inertia::render('session/create', [
            'canResetPassword' => Route::has('password.request'),
            'status' => $request->session()->get('status'),
            'socialProviders' => SocialAuthDriver::enabledProviders(),
        ]);
    }

    /**
     * Rate limiting and the failure response stay with the caller: the driver
     * only answers whether these credentials belong to somebody.
     */
    public function authenticate(Request $request): ?User
    {
        $credentials = $request->only('email', 'password');
        $provider = Auth::getProvider();
        $user = $provider->retrieveByCredentials($credentials);

        return $user instanceof User && $provider->validateCredentials($user, $credentials)
            ? $user
            : null;
    }
}
