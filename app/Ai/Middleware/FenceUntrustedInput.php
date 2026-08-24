<?php

declare(strict_types=1);

namespace App\Ai\Middleware;

use App\Support\UntrustedContent;
use Closure;
use Laravel\Ai\Prompts\AgentPrompt;

/**
 * Everything a member types is data, not instruction. The body is fenced here,
 * once, rather than at each call site, so an agent added later cannot forget.
 *
 * Retrieved documents are fenced where they are retrieved — the tool knows what
 * it fetched and can label it; this slot only ever sees the prompt body.
 */
final class FenceUntrustedInput
{
    public function handle(AgentPrompt $prompt, Closure $next): mixed
    {
        return $next($prompt->revise(
            UntrustedContent::fence($prompt->prompt, 'member request'),
        ));
    }
}
