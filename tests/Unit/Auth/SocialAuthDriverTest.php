<?php

declare(strict_types=1);

use App\Auth\Drivers\SocialAuthDriver;

it('is registered under the social key', function (): void {
    expect(resolve(SocialAuthDriver::class)->key())->toBe('social');
});

it('offers no providers while the feature is off', function (): void {
    config()->set('features.defaults.social-login-enabled', false);
    config()->set('services.github.client_id', 'client-id');

    expect(SocialAuthDriver::enabledProviders())->toBe([]);
});

it('offers only the providers that carry credentials', function (): void {
    config()->set('features.defaults.social-login-enabled', true);
    config()->set('services.github.client_id', 'client-id');
    config()->set('services.google.client_id', '');
    config()->set('services.microsoft.client_id', '');

    expect(SocialAuthDriver::enabledProviders())->toBe(['github']);
});
