<?php

declare(strict_types=1);

namespace App\Ai\Tools;

use App\Ai\Tools\Concerns\AuthorizesToolCall;
use App\Models\Organization;
use App\Models\User;
use App\Resources\ResourceRegistry;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Database\QueryException;
use Illuminate\JsonSchema\Types\Type;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;

/**
 * Reads one record of one registered resource, as the member the agent runs for.
 *
 * A record the member may not view is refused rather than reported missing: the
 * refusal is audited, and the difference between "no such record" and "not
 * yours" is not something a model gets to learn by guessing ids.
 */
final readonly class ShowResourceRecord implements Tool
{
    use AuthorizesToolCall;

    public function __construct(
        private User $user,
        private Organization $organization,
        private ResourceRegistry $registry,
    ) {}

    public function description(): string
    {
        return 'Reads a single record of one resource by its id.';
    }

    public function handle(Request $request): string
    {
        $this->authorizeFor($this->user, 'view', $this->organization);

        $resource = $this->registry->get($request->string('resource')->toString());

        try {
            $record = $resource->model()::query()->find($request->string('id')->toString());
        } catch (QueryException) {
            // A model invents ids, and an invented id need not even be a valid
            // key for the table. That is a miss, not a failure.
            $record = null;
        }

        if ($record === null) {
            return json_encode([
                'resource' => $resource->key(),
                'record' => null,
            ], JSON_THROW_ON_ERROR);
        }

        $this->authorizeFor($this->user, 'view', $record);

        return json_encode([
            'resource' => $resource->key(),
            'record' => $resource->dataClass()::from($record)->toArray(),
        ], JSON_THROW_ON_ERROR);
    }

    /**
     * @return array<string, Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'resource' => $schema->string()->enum($this->registry->keys())->required()
                ->description('The resource key the record belongs to.'),
            'id' => $schema->string()->required()->description('The record id.'),
        ];
    }
}
