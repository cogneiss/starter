<?php

declare(strict_types=1);

use App\Jobs\IndexAiDocument;
use App\Jobs\RemoveAiDocuments;
use App\Models\AiDocument;
use App\Models\Organization;
use App\Models\SavedSearch;
use App\Models\User;
use App\Resources\IndexesKnowledge;
use App\Resources\ResourceRegistry;
use App\Support\OrganizationContext;
use Illuminate\Support\Facades\Queue;
use Laravel\Ai\Embeddings;
use Laravel\Ai\Prompts\EmbeddingsPrompt;

/**
 * @template TReturn
 *
 * @param  Closure(): TReturn  $callback
 * @return TReturn
 */
function asOrganization(Organization $organization, Closure $callback): mixed
{
    return resolve(OrganizationContext::class)->runAs($organization, $callback);
}

function optIn(): void
{
    $registry = new ResourceRegistry(
        directory: base_path('tests/Fixtures/Knowledge'),
        namespace: 'Tests\\Fixtures\\Knowledge\\',
    );

    app()->instance(ResourceRegistry::class, $registry);
}

beforeEach(function (): void {
    $this->organization = Organization::factory()->create();
    $this->other = Organization::factory()->create();
    $this->user = User::factory()->forOrganization($this->organization)->create();

    Queue::fake();
});

it('queues an index job when a record of an opted-in resource is saved', function (): void {
    optIn();

    $search = SavedSearch::factory()->create(['organization_id' => $this->organization->id, 'user_id' => $this->user->id]);

    Queue::assertPushed(IndexAiDocument::class, fn (IndexAiDocument $job): bool => $job->organizationId() === $this->organization->id);
    Queue::assertPushed(IndexAiDocument::class, 1);

    $search->update(['name' => 'Renamed']);

    Queue::assertPushed(IndexAiDocument::class, 2);
});

it('writes the chunks under the record organization and replaces them on re-index', function (): void {
    optIn();

    config()->set('ai.providers.openai.key', 'fake-key-for-tests');
    config()->set('ai.default_for_embeddings', 'openai');

    Embeddings::fake(fn (EmbeddingsPrompt $prompt): array => array_map(
        fn (): array => Embeddings::fakeEmbedding(1536),
        $prompt->inputs,
    ));

    $search = SavedSearch::factory()->create(['organization_id' => $this->organization->id, 'user_id' => $this->user->id, 'name' => 'First']);

    $foreign = asOrganization($this->other, fn (): AiDocument => AiDocument::factory()->create([
        'organization_id' => $this->other->id,
        'source_type' => 'saved-searches',
        'source_id' => $search->id,
    ]));

    $indexLatest = fn (): mixed => asOrganization($this->organization, Queue::pushed(IndexAiDocument::class)->last()->handle(...));

    $indexLatest();

    $search->update(['name' => 'Second']);

    $indexLatest();

    $titles = asOrganization($this->organization, fn (): array => AiDocument::query()->pluck('title')->all());

    expect($titles)->toBe(['Second'])
        ->and(AiDocument::withoutOrganizationScope()->whereKey($foreign->id)->exists())->toBeTrue();
})->group('pgvector');

it('queues a removal when a record of an opted-in resource is deleted', function (): void {
    optIn();

    $search = SavedSearch::factory()->create(['organization_id' => $this->organization->id, 'user_id' => $this->user->id]);

    $search->delete();

    Queue::assertPushed(RemoveAiDocuments::class, fn (RemoveAiDocuments $job): bool => $job->organizationId() === $this->organization->id);
});

it('removes only the rows of that source in that organization', function (): void {
    $source = fake()->uuid();

    $rows = fn (Organization $organization, string $source): AiDocument => asOrganization($organization, fn (): AiDocument => AiDocument::factory()->create([
        'organization_id' => $organization->id,
        'source_type' => 'saved-searches',
        'source_id' => $source,
    ]));

    $rows($this->organization, $source);
    $kept = $rows($this->organization, fake()->uuid());
    $foreign = $rows($this->other, $source);

    $job = new RemoveAiDocuments($this->organization->id, 'saved-searches', $source);

    asOrganization($this->organization, $job->handle(...));

    expect(AiDocument::withoutOrganizationScope()->pluck('id')->all())
        ->toEqualCanonicalizing([$kept->id, $foreign->id])
        ->and($job->middleware())->toHaveCount(1);
})->group('pgvector');

it('queues nothing for a resource that has not opted in', function (): void {
    $this->user->update(['name' => 'Renamed']);
    $this->user->delete();

    Queue::assertNothingPushed();
});

it('keeps the shipped resources out of the knowledge corpus', function (): void {
    foreach (new ResourceRegistry()->all() as $resource) {
        expect($resource)->not->toBeInstanceOf(IndexesKnowledge::class);
    }
});
