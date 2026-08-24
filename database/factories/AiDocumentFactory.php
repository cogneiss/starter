<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\AiDocument;
use App\Models\Organization;
use Illuminate\Database\Eloquent\Factories\Factory;
use Laravel\Ai\Embeddings;

/**
 * @extends Factory<AiDocument>
 */
final class AiDocumentFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'organization_id' => Organization::factory(),
            'source_type' => 'note',
            'source_id' => (string) $this->faker->numberBetween(1, 1000),
            'title' => $this->faker->sentence(),
            'content' => $this->faker->paragraph(),
            'embedding' => Embeddings::fakeEmbedding(1536),
        ];
    }

    /**
     * A row whose embedding is the given vector, so a test can decide what a
     * similarity search finds instead of hoping random vectors cooperate.
     *
     * @param  list<float>  $embedding
     */
    public function embedding(array $embedding): self
    {
        return $this->state(fn (): array => ['embedding' => $embedding]);
    }
}
