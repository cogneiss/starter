<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\ImpersonationLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ImpersonationLog>
 */
final class ImpersonationLogFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'impersonator_user_id' => User::factory()->superAdmin(),
            'impersonated_user_id' => User::factory(),
            'organization_id' => null,
            'started_at' => now(),
            'ended_at' => null,
            'ip_address' => fake()->ipv4(),
            'user_agent' => fake()->userAgent(),
        ];
    }
}
