<?php

declare(strict_types=1);

namespace App\Support;

use App\Exceptions\BlockedEgressException;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Support\Str;

/**
 * The last gate before an agent reaches the outside world.
 *
 * A prompt injection is only worth writing if it can send something somewhere,
 * so a tool that emails, posts or fetches asks here first. Two rules: the host
 * has to be one we named, and a recipient address has to belong to a member of
 * the organization the agent is acting for. Membership, not a domain pattern —
 * "anything @acme.com" is one open signup form away from being everybody.
 */
final class AiEgress
{
    public static function assertAllowed(string $target): void
    {
        $host = self::host($target);

        if (! in_array($host, self::allowlist(), true)) {
            throw BlockedEgressException::host($target, $host);
        }

        if (str_contains($target, '@') && ! self::isMember($target)) {
            throw BlockedEgressException::recipient($target);
        }
    }

    private static function host(string $target): string
    {
        if (str_contains($target, '@')) {
            return Str::after($target, '@');
        }

        $host = parse_url($target, PHP_URL_HOST);

        return is_string($host) && $host !== ''
            ? $host
            : throw BlockedEgressException::unreadable($target);
    }

    /**
     * @return list<string>
     */
    private static function allowlist(): array
    {
        /** @var list<string> $hosts */
        $hosts = config()->array('ai.guardrails.egress');

        return $hosts;
    }

    private static function isMember(string $address): bool
    {
        $organization = resolve(OrganizationContext::class)->get();

        if (! $organization instanceof Organization) {
            return false;
        }

        return User::query()->where('email', $address)->first()
            ?->belongsToOrganization($organization) ?? false;
    }
}
