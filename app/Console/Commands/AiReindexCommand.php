<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Exceptions\UnknownResource;
use App\Jobs\IndexAiDocument;
use App\Models\Organization;
use App\Resources\IndexesKnowledge;
use App\Resources\ResourceRegistry;
use App\Support\OrganizationContext;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Model;

/**
 * Queues an index job for every record of a resource that opted in to the
 * knowledge corpus, organization by organization, for records that predate the
 * opt-in or an embedding model change.
 */
#[Description('Queue the knowledge index for every record of an opted-in resource')]
#[Signature('ai:reindex {resource : The resource key, e.g. notes}')]
final class AiReindexCommand extends Command
{
    public function handle(ResourceRegistry $registry, OrganizationContext $context): int
    {
        $key = (string) $this->argument('resource');

        try {
            $resource = $registry->get($key);
        } catch (UnknownResource $unknownResource) {
            $this->components->error($unknownResource->getMessage());

            return self::FAILURE;
        }

        if (! $resource instanceof IndexesKnowledge) {
            $this->components->error(sprintf('The [%s] resource has not opted in to the knowledge corpus.', $key));

            return self::FAILURE;
        }

        $queued = 0;

        Organization::query()->each(function (Organization $organization) use ($context, $resource, &$queued): void {
            $context->runAs($organization, function () use ($organization, $resource, &$queued): void {
                $resource->scopedQuery()->each(function (Model $record) use ($organization, $resource, &$queued): void {
                    dispatch(new IndexAiDocument($organization->id, $resource->key(), (string) $record->getKey(), $resource->knowledgeTitle($record), $resource->knowledgeContent($record)));

                    $queued++;
                });
            });
        });

        $this->components->info(sprintf('Queued %d [%s] records.', $queued, $key));

        return self::SUCCESS;
    }
}
