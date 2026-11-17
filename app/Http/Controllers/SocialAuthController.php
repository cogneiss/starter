<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\EnabledSocialProviders;
use App\Actions\LinkSocialAccount;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Laravel\Socialite\Facades\Socialite;
use Symfony\Component\HttpFoundation\RedirectResponse as SocialRedirectResponse;

/**
 * OAuth sign-in through Socialite.
 *
 * A future SAML or OIDC flow must verify the assertion against the request
 * host before trusting it. An assertion minted for one host and replayed at
 * another signs the attacker into a different organization.
 *
 * SSO users are default-deny: never provision an account for an unknown
 * identity unless the organization has explicitly opted in.
 */
final readonly class SocialAuthController
{
    public function __construct(
        private EnabledSocialProviders $providers,
        private LinkSocialAccount $accounts,
    ) {}

    public function show(string $provider): SocialRedirectResponse
    {
        $this->ensureProviderIsAvailable($provider);

        return Socialite::driver($provider)->redirect();
    }

    public function update(Request $request, string $provider): RedirectResponse
    {
        $this->ensureProviderIsAvailable($provider);

        $user = $this->accounts->handle($provider, Socialite::driver($provider)->user());

        if ($user->hasEnabledTwoFactorAuthentication()) {
            $request->session()->put([
                'login.id' => $user->getKey(),
                'login.remember' => true,
            ]);

            return to_route('two-factor.login');
        }

        Auth::login($user, remember: true);

        $request->session()->regenerate();

        return redirect()->intended(route('dashboard', absolute: false));
    }

    private function ensureProviderIsAvailable(string $provider): void
    {
        abort_unless(in_array($provider, $this->providers->handle(), strict: true), 404);
    }
}
