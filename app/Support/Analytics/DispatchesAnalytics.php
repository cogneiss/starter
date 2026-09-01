<?php

declare(strict_types=1);

namespace App\Support\Analytics;

/**
 * The one place Do Not Track is consulted server-side. Every reporter
 * dispatches through these methods, so DNT: 1 means nothing is sent no
 * matter which driver is bound.
 */
trait DispatchesAnalytics
{
    /**
     * @param  array<string, mixed>  $traits
     */
    abstract protected function sendIdentify(string $userId, array $traits): void;

    /**
     * @param  array<string, mixed>  $traits
     */
    abstract protected function sendGroup(string $groupId, array $traits): void;

    /**
     * @param  array<string, mixed>  $properties
     */
    abstract protected function sendTrack(string $event, array $properties): void;

    /**
     * @param  array<string, mixed>  $traits
     */
    public function identify(string $userId, array $traits = []): void
    {
        if ($this->suppressed()) {
            return;
        }

        $this->sendIdentify($userId, $traits);
    }

    /**
     * @param  array<string, mixed>  $traits
     */
    public function group(string $groupId, array $traits = []): void
    {
        if ($this->suppressed()) {
            return;
        }

        $this->sendGroup($groupId, $traits);
    }

    /**
     * @param  array<string, mixed>  $properties
     */
    public function track(string $event, array $properties = []): void
    {
        if ($this->suppressed()) {
            return;
        }

        $this->sendTrack($event, $properties);
    }

    private function suppressed(): bool
    {
        return request()->header('DNT') === '1';
    }
}
