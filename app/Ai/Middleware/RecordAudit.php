<?php

declare(strict_types=1);

namespace App\Ai\Middleware;

use Closure;
use Laravel\Ai\Prompts\AgentPrompt;

/**
 * Phase 4 fills this in: record the prompt and, via then(), the response it produced.
 *
 * The slot exists from phase 3 so that the pipeline order in
 * App\Ai\Agents\Concerns\HasDefaultMiddleware is fixed before any of the
 * behaviour lands on top of it.
 */
final class RecordAudit
{
    public function handle(AgentPrompt $prompt, Closure $next): mixed
    {
        return $next($prompt);
    }
}
