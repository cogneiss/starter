<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Organization;
use App\Models\WebhookEndpoint;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<WebhookEndpoint>
 */
final class WebhookEndpointFactory extends Factory
{
    protected $model = WebhookEndpoint::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'organization_id' => Organization::factory(),
            'url' => 'https://'.fake()->domainName().'/hooks',
            'description' => fake()->sentence(3),
            'events' => ['users.created'],
            'secret' => 'whsec_'.Str::random(32),
            'active' => true,
            'consecutive_failures' => 0,
            'created_by' => null,
        ];
    }

    public function inactive(): self
    {
        return $this->state(['active' => false]);
    }
}
