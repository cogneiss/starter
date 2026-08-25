<?php

declare(strict_types=1);

namespace App\Mcp\Tools;

use App\Ai\Tools\ListResourceRecords;
use App\Mcp\Tools\Concerns\DelegatesToAiTool;
use App\Models\Organization;
use App\Models\User;
use App\Resources\ResourceRegistry;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Tool;

/**
 * Lists a registered resource over MCP by handing the call to the agent's own
 * list tool.
 */
final class ListRecords extends Tool
{
    use DelegatesToAiTool;

    protected string $description = 'Lists the records of one resource in your current organization, newest first.';

    public function __construct(private readonly ResourceRegistry $registry) {}

    public function handle(Request $request): Response
    {
        return $this->answer($request, $this->delegate(...));
    }

    public function delegate(User $user, Organization $organization): ListResourceRecords
    {
        return new ListResourceRecords($user, $organization, $this->registry);
    }

    /**
     * @return array<string, mixed>
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
