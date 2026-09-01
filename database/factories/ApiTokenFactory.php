<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\ApiToken;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<ApiToken>
 */
final class ApiTokenFactory extends Factory
{
    protected $model = ApiToken::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'organization_id' => Organization::factory(),
            'tokenable_type' => User::class,
            'tokenable_id' => User::factory(),
            'created_by' => null,
            'name' => fake()->words(2, true),
            'token' => hash('sha256', Str::random(40)),
            'abilities' => [],
            'last_used_at' => null,
            'expires_at' => null,
            'revoked_at' => null,
        ];
    }

    public function revoked(): self
    {
        return $this->state(['revoked_at' => now()]);
    }

    public function expired(): self
    {
        return $this->state(['expires_at' => now()->subDay()]);
    }
}
