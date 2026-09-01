<?php

declare(strict_types=1);

namespace App\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Cache;

/**
 * The queue health check's round-trip: the check dispatches this with a
 * one-off token and a worker proves it is alive by writing the token back.
 */
final class QueueHeartbeat implements ShouldQueue
{
    use Queueable;

    public function __construct(private readonly string $token) {}

    public function handle(): void
    {
        Cache::put($this->token, true, 60);
    }
}
