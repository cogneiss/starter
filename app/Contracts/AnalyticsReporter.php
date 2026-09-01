<?php

declare(strict_types=1);

namespace App\Contracts;

/**
 * Provider-agnostic product analytics. Events carry identifiers only — a
 * model name, a key, an organization id, an attribute name — never an
 * attribute value.
 */
interface AnalyticsReporter
{
    /**
     * @param  array<string, mixed>  $traits
     */
    public function identify(string $userId, array $traits = []): void;

    /**
     * @param  array<string, mixed>  $traits
     */
    public function group(string $groupId, array $traits = []): void;

    /**
     * @param  array<string, mixed>  $properties
     */
    public function track(string $event, array $properties = []): void;

    public function reset(): void;
}
