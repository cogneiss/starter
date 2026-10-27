<?php

declare(strict_types=1);

namespace App\Ai\Middleware;

use App\Exceptions\BlockedTopicException;
use Closure;
use Illuminate\Support\Str;
use Laravel\Ai\Messages\UserMessage;
use Laravel\Ai\PendingStep;

/**
 * Refuses prompts about topics the application does not answer, before they
 * cost anything. The list is configuration rather than code because what is off
 * topic differs per deployment, and it is empty by default: a starter that
 * refuses half its prompts out of the box is worse than one that answers them.
 */
final class FilterTopics
{
    public function handle(PendingStep $step, Closure $next): mixed
    {
        if (! $step->isFirstStep()) {
            return $next($step);
        }

        $prompt = (string) collect($step->messages)->last(fn (object $message): bool => $message instanceof UserMessage)?->content;

        /** @var list<string> $denied */
        $denied = config()->array('ai.guardrails.denied_topics');

        foreach ($denied as $topic) {
            if (Str::contains($prompt, $topic, ignoreCase: true)) {
                throw BlockedTopicException::topic($topic);
            }
        }

        return $next($step);
    }
}
