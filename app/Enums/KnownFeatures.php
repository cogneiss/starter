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
    case ImpersonationEnabled = 'impersonation-enabled';
    case SocialLoginEnabled = 'social-login-enabled';

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
