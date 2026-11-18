<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Contracts\OrganizationAware;
use App\Models\AiDocument;
use App\Queue\Middleware\WithOrganizationContext;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Drops one source from the retrieval corpus once its record is gone, so the
 * assistant cannot quote text that no longer exists.
 */
final class RemoveAiDocuments implements OrganizationAware, ShouldQueue
{
    use Queueable;

    public function __construct(
        private readonly string $organizationId,
        private readonly string $sourceType,
        private readonly string $sourceId,
    ) {}

    public function organizationId(): string
    {
        return $this->organizationId;
    }

    /**
     * @return list<object>
     */
    public function middleware(): array
    {
        return [new WithOrganizationContext];
    }

    public function handle(): void
    {
        AiDocument::query()
            ->where('source_type', $this->sourceType)
            ->where('source_id', $this->sourceId)
            ->delete();
    }
}
