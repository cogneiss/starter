<?php

declare(strict_types=1);

use App\Enums\KnownFeatures;
use App\Models\FeatureOverride;
use App\Models\Organization;
use Symfony\Component\Finder\Finder;

/**
 * Every flag string referenced anywhere in the application, so a typo cannot
 * quietly resolve to false forever.
 *
 * @return list<string>
 */
function referencedFlags(): array
{
    $pattern = '/(?:Feature::(?:active|inactive|value|for\([^)]*\)->(?:active|inactive|value))|feature|usePoll)\(\s*[\'"]([a-z0-9-]+)[\'"]/';

    $flags = [];

    $finder = Finder::create()
        ->files()
        ->in([app_path(), resource_path('js')])
        ->name(['*.php', '*.ts', '*.tsx']);

    foreach ($finder as $file) {
        preg_match_all($pattern, $file->getContents(), $matches);

        foreach ($matches[1] as $flag) {
            $flags[] = $flag;
        }
    }

    return array_values(array_unique($flags));
}

it('only references flags the registry knows', function (): void {
    $known = array_map(fn (KnownFeatures $case): string => $case->value, KnownFeatures::cases());
    $referenced = referencedFlags();

    expect($referenced)->toBeArray();

    foreach ($referenced as $flag) {
        expect($known)->toContain($flag, "The flag [{$flag}] is referenced but is not a KnownFeatures case.");
    }
});

it('gives every case a configured default', function (): void {
    foreach (KnownFeatures::cases() as $case) {
        expect(config()->array('features.defaults'))->toHaveKey($case->value)
            ->and($case->value)->toMatch('/^[a-z]+(-[a-z]+)*$/');
    }
});

it('falls back to the configured default', function (): void {
    config()->set('features.defaults.impersonation-enabled', true);

    $organization = Organization::factory()->create();

    expect(KnownFeatures::ImpersonationEnabled->enabledFor($organization))->toBeTrue()
        ->and(KnownFeatures::ImpersonationEnabled->enabledFor(null))->toBeTrue();
});

it('prefers a live override over the default', function (): void {
    $organization = Organization::factory()->create();

    FeatureOverride::factory()->create([
        'organization_id' => $organization->id,
        'feature' => KnownFeatures::SocialLoginEnabled->value,
        'value' => true,
    ]);

    expect(KnownFeatures::SocialLoginEnabled->enabledFor($organization))->toBeTrue()
        ->and(KnownFeatures::SocialLoginEnabled->default())->toBeFalse();
});

it('ignores an expired override', function (): void {
    $organization = Organization::factory()->create();

    FeatureOverride::factory()->expired()->create([
        'organization_id' => $organization->id,
        'feature' => KnownFeatures::SocialLoginEnabled->value,
        'value' => true,
    ]);

    expect(KnownFeatures::SocialLoginEnabled->enabledFor($organization))->toBeFalse();
});

it('keeps one organization override away from another', function (): void {
    $organization = Organization::factory()->create();
    $other = Organization::factory()->create();

    FeatureOverride::factory()->create([
        'organization_id' => $organization->id,
        'feature' => KnownFeatures::SocialLoginEnabled->value,
        'value' => true,
    ]);

    expect(KnownFeatures::SocialLoginEnabled->enabledFor($other))->toBeFalse();
});

it('belongs to its organization', function (): void {
    $organization = Organization::factory()->create();

    $override = FeatureOverride::factory()->create(['organization_id' => $organization->id]);

    expect($override->organization->id)->toBe($organization->id);
});
