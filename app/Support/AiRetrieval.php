<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Support\Facades\DB;

/**
 * Whether retrieval is available on this machine, and why not when it is not.
 *
 * Retrieval needs two things a checkout may not have: a Postgres connection
 * carrying pgvector, and a provider that can embed. Missing either is a
 * supported state — SearchKnowledge is simply not registered, agents answer
 * without retrieval, and app:doctor says which half is missing.
 */
final class AiRetrieval
{
    public static function available(): bool
    {
        return self::unavailableReason() === null;
    }

    /**
     * The reason retrieval is off, or null when it is on.
     */
    public static function unavailableReason(): ?string
    {
        if (self::driver() !== 'pgsql') {
            return 'the database connection is not pgsql, so there is no vector index';
        }

        if (! self::hasEmbeddingProvider()) {
            return 'no embedding provider is configured';
        }

        return null;
    }

    /**
     * Whether the provider that embeddings default to carries a key.
     */
    private static function hasEmbeddingProvider(): bool
    {
        $provider = config('ai.default_for_embeddings');

        return is_string($provider) && in_array($provider, AiAvailability::providers(), true);
    }

    private static function driver(): string
    {
        return rescue(static fn (): string => DB::connection()->getDriverName(), 'unknown', report: false);
    }
}
