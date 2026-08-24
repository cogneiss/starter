<?php

declare(strict_types=1);

namespace App\Exceptions;

use RuntimeException;

/**
 * An agent tried to reach an address the guardrails do not allow. The message
 * carries the target so the audit trail says what was attempted, never a key or
 * a body.
 */
final class BlockedEgressException extends RuntimeException
{
    public static function host(string $target, string $host): self
    {
        return new self("Egress to [{$host}] is not on the allowlist, so [{$target}] was refused.");
    }

    public static function unreadable(string $target): self
    {
        return new self("Egress target [{$target}] is neither a URL nor an email address.");
    }

    public static function recipient(string $target): self
    {
        return new self("Egress to [{$target}] was refused: the address belongs to nobody in the organization.");
    }
}
