<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Organization;
use App\Models\WebhookDelivery;
use App\Models\WebhookEndpoint;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<WebhookDelivery>
 */
final class WebhookDeliveryFactory extends Factory
{
    protected $model = WebhookDelivery::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'organization_id' => Organization::factory(),
            'webhook_endpoint_id' => WebhookEndpoint::factory(),
            'event' => 'users.created',
            'payload' => ['event' => 'users.created', 'data' => []],
            'attempt' => 0,
            'status' => 'pending',
            'status_code' => null,
            'response_snippet' => null,
            'duration_ms' => null,
            'next_attempt_at' => null,
        ];
    }
}
