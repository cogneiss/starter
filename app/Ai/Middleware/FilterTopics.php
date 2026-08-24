<?php

declare(strict_types=1);

namespace App\Ai\Middleware;

use App\Exceptions\BlockedTopicException;
use Closure;
use Illuminate\Support\Str;
use Laravel\Ai\Prompts\AgentPrompt;

/**
 * Refuses prompts about topics the application does not answer, before they
 * cost anything. The list is configuration rather than code because what is off
 * topic differs per deployment, and it is empty by default: a starter that
 * refuses half its prompts out of the box is worse than one that answers them.
 */
final class FilterTopics
{
    public function handle(AgentPrompt $prompt, Closure $next): mixed
    {
        /** @var list<string> $denied */
        $denied = config()->array('ai.guardrails.denied_topics');

        foreach ($denied as $topic) {
            if (Str::contains($prompt->prompt, $topic, ignoreCase: true)) {
                throw BlockedTopicException::topic($topic);
            }
        }

        return $next($prompt);
    }
}
