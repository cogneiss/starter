<?php

declare(strict_types=1);

namespace App\Ai\Agents;

use App\Ai\Agents\Concerns\HasDefaultMiddleware;
use App\Ai\Concerns\OrganizationScopedAgent;
use App\Ai\Contracts\OrganizationScoped;
use App\Ai\Memory\AssistantMemory;
use App\Ai\Tools\ListResourceRecords;
use App\Ai\Tools\ProposeAction;
use App\Ai\Tools\RememberFact;
use App\Ai\Tools\SearchKnowledge;
use App\Ai\Tools\ShowResourceRecord;
use App\Support\AiTier;
use App\Support\UntrustedContent;
use Laravel\Ai\Attributes\CacheInstructions;
use Laravel\Ai\Attributes\CacheToolDefinitions;
use Laravel\Ai\Concerns\RemembersConversations;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\Conversational;
use Laravel\Ai\Contracts\HasMiddleware;
use Laravel\Ai\Contracts\HasTools;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Promptable;
use Laravel\Ai\Responses\AgentResponse;

/**
 * The in-app assistant: a member asks about their organization and gets an
 * answer from records they are allowed to see.
 *
 * It reads and never writes. Every tool is read-only except `ProposeAction`,
 * which only files a request for a person to confirm, and `RememberFact`, which
 * is held to the same rule. What the member told it earlier comes back in the
 * instructions, fenced as data, never as orders.
 *
 * Both cache attributes are Anthropic and Bedrock only, and each stamps one
 * breakpoint at the end of its block. The tool list is the same for every
 * member, so its breakpoint is shared. The instructions end in that member's
 * recalled facts, so their breakpoint is per member and pays off on the
 * later steps of a tool loop and on the next turn within the cache TTL.
 */
#[CacheToolDefinitions]
#[CacheInstructions]
final class Assistant implements Agent, Conversational, HasMiddleware, HasTools, OrganizationScoped
{
    use HasDefaultMiddleware;
    use OrganizationScopedAgent;
    use Promptable;
    use RemembersConversations;

    public function instructions(): string
    {
        $prompt = mb_trim((string) file_get_contents(resource_path('prompts/assistant.md')));
        $facts = new AssistantMemory($this->user, $this->organization)->recall();

        return $facts === ''
            ? $prompt
            : $prompt.PHP_EOL.PHP_EOL.UntrustedContent::fence($facts, 'assistant memory');
    }

    /**
     * @return list<Tool>
     */
    public function tools(): array
    {
        $scope = ['user' => $this->user, 'organization' => $this->organization];

        return [
            ...SearchKnowledge::registeredFor($this->user, $this->organization),
            resolve(ListResourceRecords::class, $scope),
            resolve(ShowResourceRecord::class, $scope),
            resolve(RememberFact::class, $scope),
            resolve(ProposeAction::class, $scope),
        ];
    }

    public function reply(string $message): AgentResponse
    {
        $tier = AiTier::for('standard');

        return $this->prompt($message, provider: $tier['provider'], model: $tier['model']);
    }
}
