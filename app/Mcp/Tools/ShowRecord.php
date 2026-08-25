<?php

declare(strict_types=1);

namespace App\Mcp\Tools;

use App\Ai\Tools\ShowResourceRecord;
use App\Mcp\Tools\Concerns\DelegatesToAiTool;
use App\Models\Organization;
use App\Models\User;
use App\Resources\ResourceRegistry;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Tool;

/**
 * Reads one record over MCP by handing the call to the agent's own read tool.
 */
final class ShowRecord extends Tool
{
    use DelegatesToAiTool;

    protected string $description = 'Reads a single record of one resource by its id.';

    public function __construct(private readonly ResourceRegistry $registry) {}

    public function handle(Request $request): Response
    {
        return $this->answer($request, $this->delegate(...));
    }

    public function delegate(User $user, Organization $organization): ShowResourceRecord
    {
        return new ShowResourceRecord($user, $organization, $this->registry);
    }

    /**
     * @return array<string, mixed>
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
