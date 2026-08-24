<?php

declare(strict_types=1);

namespace App\Exceptions;

use RuntimeException;

/**
 * The prompt asked about something this application does not answer. Thrown
 * before the prompt reaches a provider, so a refused topic costs nothing.
 */
final class BlockedTopicException extends RuntimeException
{
    public static function topic(string $topic): self
    {
        return new self("This assistant does not answer questions about [{$topic}].");
    }
}
