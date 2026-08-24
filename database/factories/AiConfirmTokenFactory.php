<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\AiConfirmToken;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<AiConfirmToken>
 */
final class AiConfirmTokenFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $id = (string) Str::uuid();
        $payload = ['email' => $this->faker->safeEmail(), 'role' => 'Member'];

        return [
            'id' => $id,
            'organization_id' => Organization::factory(),
            'user_id' => User::factory(),
            'action' => 'invite-member',
            'payload' => $payload,
            'signature' => AiConfirmToken::signatureFor($id, 'invite-member', $payload),
            'summary' => 'Invite someone to the organization.',
            'expires_at' => now()->addMinutes(15),
            'consumed_at' => null,
            'created_at' => now(),
        ];
    }
}
