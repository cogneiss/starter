<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Jobs\IndexAiDocument;
use App\Jobs\RemoveAiDocuments;
use App\Resources\IndexesKnowledge;
use App\Resources\ResourceRegistry;
use Illuminate\Database\Eloquent\Model;

/**
 * Keeps the retrieval corpus in step with the records of resources that opted
 * in through {@see IndexesKnowledge}. Both jobs wait for the surrounding
 * transaction to commit, so a rolled-back save never reaches the corpus.
 */
final readonly class SyncKnowledgeIndex
{
    public function __construct(private ResourceRegistry $registry) {}

    /**
     * @param  array{0: Model}  $payload
     */
    public function handle(string $event, array $payload): void
    {
        $record = $payload[0];
        $resource = $this->registry->forModel($record);
        $organizationId = $record->getAttribute('organization_id');

        if (! $resource instanceof IndexesKnowledge || ! is_string($organizationId)) {
            return;
        }

        if (str_starts_with($event, 'eloquent.deleted')) {
            dispatch(new RemoveAiDocuments($organizationId, $resource->key(), (string) $record->getKey()))->afterCommit();

            return;
        }

        dispatch(new IndexAiDocument($organizationId, $resource->key(), (string) $record->getKey(), $resource->knowledgeTitle($record), $resource->knowledgeContent($record)))->afterCommit();
    }
}
