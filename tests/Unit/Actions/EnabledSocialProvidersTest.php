<?php

declare(strict_types=1);

use App\Actions\EnabledSocialProviders;

it('offers no providers while the feature is off', function (): void {
    config()->set('features.defaults.social-login-enabled', false);
    config()->set('services.github.client_id', 'client-id');

    expect(resolve(EnabledSocialProviders::class)->handle())->toBe([]);
});

it('offers only the providers that carry credentials', function (): void {
    config()->set('features.defaults.social-login-enabled', true);
    config()->set('services.github.client_id', 'client-id');
    config()->set('services.google.client_id', '');

    expect(resolve(EnabledSocialProviders::class)->handle())->toBe(['github']);
});
