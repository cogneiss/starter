<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\AiMemory;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AiMemory>
 */
final class AiMemoryFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'organization_id' => Organization::factory(),
            'user_id' => User::factory(),
            'key' => $this->faker->unique()->word(),
            'value' => $this->faker->sentence(),
            'source' => 'tool',
            'expires_at' => null,
        ];
    }
}
