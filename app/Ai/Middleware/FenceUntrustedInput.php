<?php

declare(strict_types=1);

namespace App\Ai\Middleware;

use App\Support\UntrustedContent;
use Closure;
use Laravel\Ai\Messages\Message;
use Laravel\Ai\Messages\UserMessage;
use Laravel\Ai\PendingStep;

/**
 * Everything a member types is data, not instruction. The body is fenced here,
 * once, rather than at each call site, so an agent added later cannot forget.
 *
 * A fenced history applies to one step only, so every step is fenced. Earlier
 * turns of a remembered conversation are member text too and get the same fence.
 *
 * Retrieved documents are fenced where they are retrieved — the tool knows what
 * it fetched and can label it; this slot only ever sees the member's messages.
 */
final class FenceUntrustedInput
{
    public function handle(PendingStep $step, Closure $next): mixed
    {
        return $next($step->withMessages(array_map(
            fn (Message $message): Message => $message instanceof UserMessage
                ? new UserMessage(UntrustedContent::fence((string) $message->content, 'member request'), $message->attachments)
                : $message,
            $step->messages,
        )));
    }
}
