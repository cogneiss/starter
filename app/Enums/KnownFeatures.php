<?php

declare(strict_types=1);

namespace App\Enums;

use App\Models\FeatureOverride;
use App\Models\Organization;
use Illuminate\Contracts\Database\Query\Builder;

/**
 * Every feature flag the application knows about. A flag that is not a case
 * here resolves to false forever, so KnownFeaturesTest fails the build when
 * code references a string that is not listed.
 */
enum KnownFeatures: string
{
    case AiBriefingEnabled = 'ai-briefing-enabled';
    case ImpersonationEnabled = 'impersonation-enabled';
    case SocialLoginEnabled = 'social-login-enabled';

    /**
     * The Pennant feature naming an organization's API rate tier. A
     * string-valued feature, not a boolean flag, so it is a constant rather
     * than a case; every reference goes through it, so a typo cannot exist.
     * Its default lives in config/api.php as `rate_tiers.default`.
     */
    public const string API_RATE_TIER = 'api-rate-tier';

    /**
     * A live override wins; otherwise the configured default applies.
     */
    public function enabledFor(?Organization $organization): bool
    {
        if (! $organization instanceof Organization) {
            return $this->default();
        }

        $override = FeatureOverride::query()
            ->where('organization_id', $organization->id)
            ->where('feature', $this->value)
            ->where(fn (Builder $query): Builder => $query
                ->whereNull('expires_at')
                ->orWhere('expires_at', '>', now()))
            ->first();

        return $override instanceof FeatureOverride ? $override->value : $this->default();
    }

    public function default(): bool
    {
        return config()->boolean('features.defaults.'.$this->value);
    }
}
