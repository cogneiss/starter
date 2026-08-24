<?php

declare(strict_types=1);

namespace Tests\Fixtures\Ai;

use App\Ai\Tools\Concerns\AuthorizesToolCall;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;

/**
 * A tool shaped exactly like the ones phase 6 adds — constructed for one member
 * of one organization, asking the policy before it reads anything — kept here so
 * the authorization concern can be exercised before a product tool exists.
 */
final readonly class FixtureTool implements Tool
{
    use AuthorizesToolCall;

    public function __construct(private User $user, private Organization $organization) {}

    public function description(): string
    {
        return 'Reads the name of the organization the agent is acting for.';
    }

    public function handle(Request $request): string
    {
        $this->authorizeFor($this->user, 'view', $this->organization);

        return $this->organization->name;
    }

    /**
     * @return array<string, \Illuminate\JsonSchema\Types\Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [];
    }
}
