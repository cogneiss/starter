<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\ImportBatch;
use App\Models\ImportRow;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ImportRow>
 */
final class ImportRowFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'import_batch_id' => ImportBatch::factory(),
            'line_number' => fake()->numberBetween(2, 100),
            'data' => ['email' => fake()->unique()->safeEmail(), 'role' => 'Member'],
            'status' => ImportRow::PENDING,
            'errors' => null,
        ];
    }
}
