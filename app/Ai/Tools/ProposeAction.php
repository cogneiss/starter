<?php

declare(strict_types=1);

namespace App\Ai\Tools;

use App\Actions\CreateConfirmToken;
use App\Ai\Blocks\ConfirmBlock;
use App\Ai\ConfirmableActions;
use App\Ai\Tools\Concerns\AuthorizesToolCall;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;

/**
 * The only tool allowed to write, and the only thing it writes is a proposal.
 *
 * An agent never performs an action. It proposes one, which is a confirm token
 * plus the block that renders it; the write itself happens later, in a request
 * the person made, behind the same permission checked again.
 */
final readonly class ProposeAction implements Tool
{
    use AuthorizesToolCall;

    public function __construct(
        private User $user,
        private Organization $organization,
        private CreateConfirmToken $tokens,
    ) {}

    public function description(): string
    {
        return 'Proposes an action for the person to confirm. Nothing is performed until they do.';
    }

    public function handle(Request $request): string
    {
        $this->authorizeFor($this->user, 'view', $this->organization);

        // Only the named fields of an action are ever proposed, so a payload
        // carrying integer keys is not a payload this can build an action from.
        $fields = array_filter($request->array('fields'), is_string(...), ARRAY_FILTER_USE_KEY);

        $token = $this->tokens->handle($this->user, $request->string('action')->toString(), $fields);

        return json_encode(new ConfirmBlock($token->id)->toArray(), JSON_THROW_ON_ERROR);
    }

    /**
     * @return array<string, Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'action' => $schema->string()->enum(array_keys(ConfirmableActions::all()))->required()
                ->description('The action to propose.'),
            'fields' => $schema->object()->description('The action\'s fields, keyed by name.'),
        ];
    }
}
