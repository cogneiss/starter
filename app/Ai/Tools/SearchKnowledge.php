<?php

declare(strict_types=1);

namespace App\Ai\Tools;

use App\Ai\Tools\Concerns\AuthorizesToolCall;
use App\Models\AiDocument;
use App\Models\Organization;
use App\Models\User;
use App\Support\AiRetrieval;
use App\Support\UntrustedContent;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;

/**
 * Nearest-neighbour search over the organization's own documents.
 *
 * Written as a wrapper rather than as the SDK's SimilaritySearch tool on
 * purpose: that tool queries the table, and the table is only safe through the
 * model, whose global scope is what keeps one organization's nearest neighbours
 * out of another's answer. What comes back is content a customer wrote, so it
 * reaches the prompt fenced.
 */
final readonly class SearchKnowledge implements Tool
{
    use AuthorizesToolCall;

    private const int LIMIT = 10;

    private const float MIN_SIMILARITY = 0.4;

    public function __construct(
        private User $user,
        private Organization $organization,
    ) {}

    /**
     * The tool, or nothing at all when this machine cannot retrieve. An agent
     * with no SearchKnowledge answers from what it was given; an agent holding
     * one that throws answers with a stack trace.
     *
     * @return list<self>
     */
    public static function registeredFor(User $user, Organization $organization): array
    {
        return AiRetrieval::available() ? [new self($user, $organization)] : [];
    }

    public function description(): string
    {
        return 'Searches the organization\'s own documents for passages related to a question.';
    }

    public function handle(Request $request): string
    {
        $this->authorizeFor($this->user, 'view', $this->organization);

        $query = $request->string('query')->toString();

        $documents = AiDocument::query()
            ->whereVectorSimilarTo('embedding', $query, minSimilarity: self::MIN_SIMILARITY)
            ->limit(self::LIMIT)
            ->get();

        if ($documents->isEmpty()) {
            return 'No document in this organization matched that question.';
        }

        return $documents
            ->map(fn (AiDocument $document): string => UntrustedContent::fence(
                $document->title.PHP_EOL.$document->content,
                'document',
            ))
            ->implode(PHP_EOL);
    }

    /**
     * @return array<string, Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'query' => $schema->string()->required()
                ->description('What to look for, in the words of the question.'),
        ];
    }
}
