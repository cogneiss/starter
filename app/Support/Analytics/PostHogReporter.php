<?php

declare(strict_types=1);

namespace App\Support\Analytics;

use App\Contracts\AnalyticsReporter;
use App\Jobs\SendAnalyticsEvent;
use Illuminate\Support\Facades\Auth;

/**
 * PostHog over its plain capture endpoint — no package, one queued POST per
 * event. Identity and groups ride the same endpoint as special event names.
 */
final class PostHogReporter implements AnalyticsReporter
{
    use DispatchesAnalytics;

    public function reset(): void
    {
        // Identity is per-request on the server; there is nothing to clear.
    }

    protected function sendIdentify(string $userId, array $traits): void
    {
        $this->capture('$identify', ['$set' => $traits], $userId);
    }

    protected function sendGroup(string $groupId, array $traits): void
    {
        $this->capture('$groupidentify', [
            '$group_type' => 'organization',
            '$group_key' => $groupId,
            '$group_set' => $traits,
        ]);
    }

    protected function sendTrack(string $event, array $properties): void
    {
        $this->capture($event, $properties);
    }

    /**
     * @param  array<string, mixed>  $properties
     */
    private function capture(string $event, array $properties, ?string $distinctId = null): void
    {
        SendAnalyticsEvent::dispatch([
            'event' => $event,
            'distinct_id' => $distinctId ?? Auth::id() ?? 'anonymous',
            'properties' => $properties,
        ]);
    }
}
