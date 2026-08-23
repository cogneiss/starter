<?php

declare(strict_types=1);

use App\Enums\KnownFeatures;
use App\Models\FeatureOverride;
use App\Models\Organization;
use App\Support\OrganizationContext;
use Laravel\Pennant\Feature;

it('resolves flags for the bound organization', function (): void {
    $organization = Organization::factory()->create();

    FeatureOverride::factory()->create([
        'organization_id' => $organization->id,
        'feature' => KnownFeatures::ImpersonationEnabled->value,
        'value' => true,
    ]);

    resolve(OrganizationContext::class)->runAs($organization, function (): void {
        expect(Feature::active('impersonation-enabled'))->toBeTrue()
            ->and(Feature::active('social-login-enabled'))->toBeFalse();
    });
});

it('resolves flags for an explicit organization', function (): void {
    $organization = Organization::factory()->create();

    FeatureOverride::factory()->create([
        'organization_id' => $organization->id,
        'feature' => KnownFeatures::SocialLoginEnabled->value,
        'value' => true,
    ]);

    expect(Feature::for($organization)->active('social-login-enabled'))->toBeTrue();
});

it('falls back to defaults with no organization bound', function (): void {
    expect(Feature::active('impersonation-enabled'))->toBeFalse();
});
