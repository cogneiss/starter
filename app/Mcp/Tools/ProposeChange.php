<?php

declare(strict_types=1);

namespace App\Mcp\Tools;

use App\Ai\ConfirmableActions;
use App\Ai\Tools\ProposeAction;
use App\Mcp\Tools\Concerns\DelegatesToAiTool;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Tool;

/**
 * Proposes a write over MCP, and proposes it exactly the way the web UI does.
 *
 * An MCP client is a program on somebody's laptop; it does not get a write path
 * the browser does not have. What comes back is a confirm token, and the token
 * is spent later by the person, in a request they made.
 */
final class ProposeChange extends Tool
{
    use DelegatesToAiTool;

    protected string $description = 'Proposes an action for the person to confirm. Nothing is performed until they do.';

    public function handle(Request $request): Response
    {
        return $this->answer($request, $this->delegate(...));
    }

    public function delegate(User $user, Organization $organization): ProposeAction
    {
        return resolve(ProposeAction::class, ['user' => $user, 'organization' => $organization]);
    }

    /**
     * @return array<string, mixed>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'action' => $schema->string()->enum(array_keys(ConfirmableActions::all()))->required()
                ->description('The action to propose.'),
            'fields' => $schema->object()->description("The action's fields, keyed by name."),
        ];
    }
}
