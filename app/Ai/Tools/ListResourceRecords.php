<?php

declare(strict_types=1);

namespace App\Ai\Tools;

use App\Ai\Tools\Concerns\AuthorizesToolCall;
use App\Models\Organization;
use App\Models\User;
use App\Resources\ResourceRegistry;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Database\Eloquent\Model;
use Illuminate\JsonSchema\Types\Type;
use Illuminate\Support\Facades\Gate;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Spatie\LaravelData\Data;

/**
 * Reads a page of one registered resource, as the member the agent runs for.
 *
 * Two gates, not one: the member has to be allowed to use the tool at all, and
 * every row has to survive the same policy the UI asks. The second gate is what
 * keeps another organization's rows out even for models the global scope does
 * not cover.
 */
final readonly class ListResourceRecords implements Tool
{
    use AuthorizesToolCall;

    /**
     * The most rows read before the policy filter runs. A tool answers a model,
     * not a report: nothing downstream wants more than a page of context.
     */
    private const int CEILING = 200;

    public function __construct(
        private User $user,
        private Organization $organization,
        private ResourceRegistry $registry,
    ) {}

    public function description(): string
    {
        return 'Lists the records of one resource in the current organization, newest first.';
    }

    public function handle(Request $request): string
    {
        $this->authorizeFor($this->user, 'view', $this->organization);

        $resource = $this->registry->get($request->string('resource')->toString());

        $limit = max(1, min(50, $request->integer('limit', 25)));

        $records = $resource->model()::query()
            ->latest()
            ->limit(self::CEILING)
            ->get()
            ->filter(fn (Model $record): bool => Gate::forUser($this->user)->allows('view', $record))
            ->take($limit)
            ->map(fn (Model $record): Data => $resource->dataClass()::from($record));

        return json_encode([
            'resource' => $resource->key(),
            'records' => $records->map(fn (Data $data): array => $data->toArray())->values()->all(),
        ], JSON_THROW_ON_ERROR);
    }

    /**
     * @return array<string, Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'resource' => $schema->string()->enum($this->registry->keys())->required()
                ->description('The resource key to list.'),
            'limit' => $schema->integer()->min(1)->max(50)
                ->description('How many records to return. Defaults to 25.'),
        ];
    }
}
