<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\AnnouncementLevel;
use App\Models\Announcement;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Announcement>
 */
final class AnnouncementFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'organization_id' => null,
            'title' => fake()->sentence(3),
            'body' => fake()->sentence(),
            'level' => AnnouncementLevel::Info,
            'starts_at' => now()->subHour(),
            'ends_at' => null,
        ];
    }
}
