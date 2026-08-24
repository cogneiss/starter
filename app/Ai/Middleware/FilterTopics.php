<?php

declare(strict_types=1);

namespace App\Ai\Middleware;

use Closure;
use Laravel\Ai\Prompts\AgentPrompt;

/**
 * Phase 5 fills this in: refuse prompts outside the topics this application answers.
 *
 * The slot exists from phase 3 so that the pipeline order in
 * App\Ai\Agents\Concerns\HasDefaultMiddleware is fixed before any of the
 * behaviour lands on top of it.
 */
final class FilterTopics
{
    public function handle(AgentPrompt $prompt, Closure $next): mixed
    {
        return $next($prompt);
    }
}
