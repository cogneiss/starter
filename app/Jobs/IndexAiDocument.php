<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Contracts\OrganizationAware;
use App\Models\AiDocument;
use App\Queue\Middleware\WithOrganizationContext;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Str;
use Laravel\Ai\Embeddings;

/**
 * Embeds one piece of organization content into the retrieval corpus.
 *
 * Embedding is a network call per chunk, so it never happens in a request.
 * Long content becomes several rows: an embedding of a whole document averages
 * everything in it into one point, and retrieval then matches nothing well.
 */
final class IndexAiDocument implements OrganizationAware, ShouldQueue
{
    use Queueable;

    /**
     * Roughly a few paragraphs. Big enough to keep a thought together, small
     * enough that the vector still describes it.
     */
    private const int CHUNK = 1000;

    public function __construct(
        private readonly string $organizationId,
        private readonly string $sourceType,
        private readonly string $sourceId,
        private readonly string $title,
        private readonly string $content,
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
        $chunks = self::chunk($this->content);

        // Re-indexing replaces: a source that changed must not leave the old
        // text in the corpus to be retrieved alongside the new.
        AiDocument::query()
            ->where('source_type', $this->sourceType)
            ->where('source_id', $this->sourceId)
            ->delete();

        $embeddings = Embeddings::for($chunks)->dimensions(1536)->generate();

        foreach ($chunks as $index => $chunk) {
            AiDocument::query()->create([
                'organization_id' => $this->organizationId,
                'source_type' => $this->sourceType,
                'source_id' => $this->sourceId,
                'title' => $this->title,
                'content' => $chunk,
                'embedding' => $embeddings->embeddings[$index],
            ]);
        }
    }

    /**
     * @return list<string>
     */
    private static function chunk(string $content): array
    {
        return array_values(array_filter(array_map(
            fn (string $chunk): string => Str::of($chunk)->trim()->toString(),
            mb_str_split($content, self::CHUNK),
        )));
    }
}
