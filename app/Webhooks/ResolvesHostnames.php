<?php

declare(strict_types=1);

namespace App\Webhooks;

/**
 * DNS resolution behind a contract so the SSRF guard's send-time re-check is
 * testable: a test binds a resolver that answers differently at validation
 * and at send, and the guard must still refuse the private answer.
 */
interface ResolvesHostnames
{
    /**
     * Every IP address the hostname resolves to, empty when it resolves to
     * nothing. An IP literal resolves to itself.
     *
     * @return list<string>
     */
    public function resolve(string $hostname): array;
}
