<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Auth\Drivers\SocialAuthDriver;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\RedirectResponse as SocialRedirectResponse;

final readonly class SocialAuthController
{
    public function __construct(private SocialAuthDriver $driver) {}

    public function show(Request $request, string $provider): SocialRedirectResponse
    {
        $this->ensureProviderIsAvailable($provider);

        return $this->driver->redirect($request);
    }

    public function update(Request $request, string $provider): RedirectResponse
    {
        $this->ensureProviderIsAvailable($provider);

        $user = $this->driver->authenticate($request);

        Auth::login($user, remember: true);

        $request->session()->regenerate();

        return redirect()->intended(route('dashboard', absolute: false));
    }

    private function ensureProviderIsAvailable(string $provider): void
    {
        abort_unless(in_array($provider, SocialAuthDriver::enabledProviders(), strict: true), 404);
    }
}
