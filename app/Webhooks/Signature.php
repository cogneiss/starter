<?php

declare(strict_types=1);

namespace App\Webhooks;

/**
 * HMAC-SHA256 over `<timestamp>.<raw body>`. The timestamp is part of the
 * signed material and verification refuses timestamps outside the tolerance
 * window, so a captured request cannot be replayed later even though its
 * signature is genuine.
 */
final class Signature
{
    public static function sign(string $body, string $secret, int $timestamp): string
    {
        return hash_hmac('sha256', $timestamp.'.'.$body, $secret);
    }

    public static function verify(string $body, string $secret, int $timestamp, string $signature, ?int $tolerance = null): bool
    {
        $tolerance ??= config()->integer('webhooks.tolerance');

        if (abs(time() - $timestamp) > $tolerance) {
            return false;
        }

        return hash_equals(self::sign($body, $secret, $timestamp), $signature);
    }
}
