<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\KnownFeatures;
use App\Models\FeatureOverride;
use App\Models\Organization;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<FeatureOverride>
 */
final class FeatureOverrideFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'organization_id' => Organization::factory(),
            'feature' => KnownFeatures::ImpersonationEnabled->value,
            'value' => true,
            'expires_at' => null,
        ];
    }

    public function expired(): self
    {
        return $this->state(fn (array $attributes): array => [
            'expires_at' => now()->subDay(),
        ]);
    }
}
