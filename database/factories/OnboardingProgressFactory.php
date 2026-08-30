<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\OnboardingProgress;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<OnboardingProgress>
 */
final class OnboardingProgressFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'organization_id' => Organization::factory(),
            'user_id' => User::factory(),
            'skipped_at' => null,
            'completed_at' => null,
        ];
    }

    public function skipped(): self
    {
        return $this->state(['skipped_at' => now()]);
    }
}
