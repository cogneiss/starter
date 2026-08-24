<?php

declare(strict_types=1);

namespace Tests\Fixtures\Ai;

use App\Ai\Agents\Concerns\HasDefaultMiddleware;
use App\Ai\Concerns\OrganizationScopedAgent;
use App\Ai\Contracts\OrganizationScoped;
use App\Ai\Tools\SearchKnowledge;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\HasMiddleware;
use Laravel\Ai\Contracts\HasTools;
use Laravel\Ai\Promptable;

/**
 * An agent that retrieves — shaped the way phase 12's product agents are, and
 * carrying SearchKnowledge only when this machine can actually retrieve.
 */
final class RetrievalFixtureAgent implements Agent, HasMiddleware, HasTools, OrganizationScoped
{
    use HasDefaultMiddleware;
    use OrganizationScopedAgent;
    use Promptable;

    public function instructions(): string
    {
        return 'You answer from the organization\'s own documents.';
    }

    /**
     * @return list<object>
     */
    public function tools(): array
    {
        return SearchKnowledge::registeredFor($this->user, $this->organization);
    }
}
