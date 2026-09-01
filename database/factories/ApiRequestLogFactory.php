<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\ApiRequestLog;
use App\Models\Organization;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ApiRequestLog>
 */
final class ApiRequestLogFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'organization_id' => Organization::factory(),
            'api_token_id' => null,
            'method' => 'GET',
            'path' => 'api/v1/users',
            'resource' => 'users',
            'status' => 200,
            'duration_ms' => 12,
            'created_at' => now(),
        ];
    }
}
