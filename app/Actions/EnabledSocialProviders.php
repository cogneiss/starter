<?php

declare(strict_types=1);

namespace App\Actions;

use App\Enums\KnownFeatures;
use Laravel\Pennant\Feature;

final readonly class EnabledSocialProviders
{
    /**
     * The providers this application knows how to talk to.
     *
     * @var list<string>
     */
    public const array PROVIDERS = ['google', 'github'];

    /**
     * The providers a visitor may actually use right now. A provider without
     * credentials in the environment does not exist.
     *
     * @return list<string>
     */
    public function handle(): array
    {
        if (! Feature::active(KnownFeatures::SocialLoginEnabled->value)) {
            return [];
        }

        return array_values(array_filter(
            self::PROVIDERS,
            fn (string $provider): bool => config()->string('services.'.$provider.'.client_id', '') !== '',
        ));
    }
}
