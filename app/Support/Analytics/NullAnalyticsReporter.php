<?php

declare(strict_types=1);

namespace App\Support\Analytics;

use App\Contracts\AnalyticsReporter;

/**
 * The default driver: an in-memory record, so tests can assert what would
 * have been sent and production without a provider key sends nothing at all.
 */
final class NullAnalyticsReporter implements AnalyticsReporter
{
    use DispatchesAnalytics;

    /** @var list<array{type: string, name: string, payload: array<string, mixed>}> */
    public array $events = [];

    public function reset(): void
    {
        $this->events[] = ['type' => 'reset', 'name' => '', 'payload' => []];
    }

    protected function sendIdentify(string $userId, array $traits): void
    {
        $this->events[] = ['type' => 'identify', 'name' => $userId, 'payload' => $traits];
    }

    protected function sendGroup(string $groupId, array $traits): void
    {
        $this->events[] = ['type' => 'group', 'name' => $groupId, 'payload' => $traits];
    }

    protected function sendTrack(string $event, array $properties): void
    {
        $this->events[] = ['type' => 'track', 'name' => $event, 'payload' => $properties];
    }
}
