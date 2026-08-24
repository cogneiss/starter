<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\AiAuditStatus;
use App\Models\AiAuditLog;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AiAuditLog>
 */
final class AiAuditLogFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'organization_id' => Organization::factory(),
            'user_id' => User::factory(),
            'agent' => 'App\Ai\Agents\ExampleAgent',
            'provider' => 'anthropic',
            'model' => 'claude-haiku-4-5-20251001',
            'tier' => 'cheap',
            'prompt_tokens' => 100,
            'completion_tokens' => 20,
            'total_tokens' => 120,
            'cost_micros' => 200,
            'duration_ms' => 42,
            'status' => AiAuditStatus::Ok,
            'blocked_reason' => null,
            'tool_calls' => [],
            'created_at' => now(),
        ];
    }

    public function blocked(string $reason = 'Hourly request limit reached.'): self
    {
        return $this->state(fn (): array => [
            'status' => AiAuditStatus::Blocked,
            'blocked_reason' => $reason,
            'prompt_tokens' => 0,
            'completion_tokens' => 0,
            'total_tokens' => 0,
            'cost_micros' => 0,
        ]);
    }
}
