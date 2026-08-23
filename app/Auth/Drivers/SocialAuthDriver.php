<?php

declare(strict_types=1);

namespace App\Auth\Drivers;

use App\Actions\LinkSocialAccount;
use App\Auth\Contracts\AuthDriver;
use App\Enums\KnownFeatures;
use App\Models\User;
use Illuminate\Http\Request;
use Laravel\Pennant\Feature;
use Laravel\Socialite\Facades\Socialite;
use Symfony\Component\HttpFoundation\RedirectResponse;

/**
 * OAuth sign-in through Socialite. Which providers exist is a deployment
 * decision: a provider without credentials in the environment does not exist.
 */
final readonly class SocialAuthDriver implements AuthDriver
{
    /**
     * The providers this application knows how to talk to.
     *
     * @var array<int, string>
     */
    public const array PROVIDERS = ['google', 'github', 'microsoft'];

    public function __construct(private LinkSocialAccount $accounts) {}

    /**
     * The providers a visitor may actually use right now.
     *
     * @return array<int, string>
     */
    public static function enabledProviders(): array
    {
        if (! Feature::active(KnownFeatures::SocialLoginEnabled->value)) {
            return [];
        }

        return array_values(array_filter(
            self::PROVIDERS,
            fn (string $provider): bool => config()->string('services.'.$provider.'.client_id', '') !== '',
        ));
    }

    public function key(): string
    {
        return 'social';
    }

    public function redirect(Request $request): RedirectResponse
    {
        return Socialite::driver($this->provider($request))->redirect();
    }

    public function authenticate(Request $request): User
    {
        $provider = $this->provider($request);

        return $this->accounts->handle($provider, Socialite::driver($provider)->user());
    }

    private function provider(Request $request): string
    {
        return (string) $request->route('provider');
    }
}
