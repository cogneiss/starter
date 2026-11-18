<?php

declare(strict_types=1);

namespace App\Resources;

use App\Jobs\IndexAiDocument;
use App\Jobs\RemoveAiDocuments;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Database\Eloquent\Model;

/**
 * Keeps the retrieval corpus in step with the records of resources that opted
 * in through {@see IndexesKnowledge}. Both jobs wait for the surrounding
 * transaction to commit, so a rolled-back save never reaches the corpus.
 */
final readonly class SyncKnowledgeIndex
{
    public function __construct(private ResourceRegistry $registry) {}

    public function subscribe(Dispatcher $events): void
    {
        foreach ($this->registry->knowledgeEvents() as $event) {
            $events->listen($event, str_starts_with($event, 'eloquent.deleted') ? $this->deleted(...) : $this->saved(...));
        }
    }

    public function saved(Model $record): void
    {
        $resource = $this->registry->forModel($record);
        assert($resource instanceof IndexesKnowledge);

        dispatch(IndexAiDocument::forRecord($resource, $record, $this->organizationId($record)))->afterCommit();
    }

    public function deleted(Model $record): void
    {
        $resource = $this->registry->forModel($record);
        assert($resource instanceof IndexesKnowledge);

        dispatch(new RemoveAiDocuments($this->organizationId($record), $resource->key(), IndexAiDocument::sourceId($record)))->afterCommit();
    }

    private function organizationId(Model $record): string
    {
        $organizationId = $record->getAttribute('organization_id');
        assert(is_string($organizationId));

        return $organizationId;
    }
}
