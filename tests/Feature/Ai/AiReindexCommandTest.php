<?php

declare(strict_types=1);

use App\Jobs\IndexAiDocument;
use App\Models\Organization;
use App\Models\SavedSearch;
use App\Models\User;
use App\Resources\ResourceRegistry;
use Illuminate\Support\Facades\Queue;

beforeEach(function (): void {
    $this->organization = Organization::factory()->create();
    $this->other = Organization::factory()->create();
    $this->user = User::factory()->forOrganization($this->organization)->create();

    Queue::fake();
});

it('reindexes every record of an opted-in resource per organization', function (): void {
    app()->instance(ResourceRegistry::class, new ResourceRegistry(
        directory: base_path('tests/Fixtures/Knowledge'),
        namespace: 'Tests\\Fixtures\\Knowledge\\',
    ));

    SavedSearch::factory()->count(2)->create(['organization_id' => $this->organization->id, 'user_id' => $this->user->id]);
    SavedSearch::factory()->create(['organization_id' => $this->other->id, 'user_id' => User::factory()->forOrganization($this->other)->create()->id]);

    Queue::fake();

    $this->artisan('ai:reindex', ['resource' => 'saved-searches'])
        ->expectsOutputToContain('Queued 3 [saved-searches] records.')
        ->assertSuccessful();

    Queue::assertPushed(IndexAiDocument::class, 3);
    Queue::assertPushed(IndexAiDocument::class, fn (IndexAiDocument $job): bool => $job->organizationId() === $this->other->id);
});

it('refuses a resource that has not opted in, and one that does not exist', function (): void {
    $this->artisan('ai:reindex', ['resource' => 'organizations'])
        ->expectsOutputToContain('has not opted in')
        ->assertFailed();

    $this->artisan('ai:reindex', ['resource' => 'nope'])
        ->expectsOutputToContain('No resource adapter')
        ->assertFailed();

    Queue::assertNothingPushed();
});
