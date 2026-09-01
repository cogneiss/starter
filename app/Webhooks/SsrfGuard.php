<?php

declare(strict_types=1);

namespace App\Webhooks;

/**
 * Refuses webhook URLs that would make the server talk to itself or its
 * network. The same range check runs twice: against the URL at validation,
 * and against the freshly resolved IP immediately before each send — a DNS
 * answer that changed between the two is refused, and the connection is
 * pinned to the exact IP that passed.
 */
final readonly class SsrfGuard
{
    public function __construct(private ResolvesHostnames $resolver) {}

    /**
     * Private and reserved ranges are both refused: loopback, link-local,
     * RFC 1918 and their IPv6 equivalents all fail the flags.
     */
    public function isPublicIp(string $ip): bool
    {
        return filter_var(
            $ip,
            FILTER_VALIDATE_IP,
            FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE,
        ) !== false;
    }

    /**
     * The IP to pin the connection to, or null when the URL must be refused.
     * Every resolved address must be public — one private A record among
     * public ones is a rebinding attempt, not an acceptable answer.
     */
    public function checkedIp(string $hostname): ?string
    {
        $addresses = $this->resolver->resolve($hostname);

        if ($addresses === []) {
            return null;
        }

        foreach ($addresses as $address) {
            if (! $this->isPublicIp($address)) {
                return null;
            }
        }

        return $addresses[0];
    }

    public function allows(string $url): bool
    {
        if (parse_url($url, PHP_URL_SCHEME) !== 'https') {
            return false;
        }

        $host = parse_url($url, PHP_URL_HOST);

        return is_string($host) && $this->checkedIp($host) !== null;
    }
}
