<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Organization;
use App\Models\SavedSearch;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SavedSearch>
 */
final class SavedSearchFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'organization_id' => Organization::factory(),
            'user_id' => User::factory(),
            'resource' => 'organization-members',
            'name' => fake()->words(2, true),
            'query' => ['q' => fake()->word()],
            'is_default' => false,
        ];
    }

    public function default(): self
    {
        return $this->state(['is_default' => true]);
    }
}
