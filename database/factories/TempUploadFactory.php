<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Contracts\FileScanner;
use App\Models\Organization;
use App\Models\TempUpload;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TempUpload>
 */
final class TempUploadFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'organization_id' => Organization::factory(),
            'user_id' => User::factory(),
            'disk' => 'temp-uploads',
            'path' => 'temp/'.fake()->uuid().'.csv',
            'original_name' => 'members.csv',
            'mime' => 'text/csv',
            'size' => fake()->numberBetween(100, 10_000),
            'scanned_at' => null,
            'scan_result' => null,
            'promoted_at' => null,
            'expires_at' => now()->addDay(),
        ];
    }

    public function clean(): self
    {
        return $this->state([
            'scanned_at' => now(),
            'scan_result' => FileScanner::CLEAN,
        ]);
    }

    public function infected(): self
    {
        return $this->state([
            'scanned_at' => now(),
            'scan_result' => FileScanner::INFECTED,
        ]);
    }

    public function expired(): self
    {
        return $this->state(['expires_at' => now()->subDay()]);
    }
}
