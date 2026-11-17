<?php

declare(strict_types=1);

use Spatie\Health\ResultStores\InMemoryHealthResultStore;

return [

    /*
    |--------------------------------------------------------------------------
    | Health checks
    |--------------------------------------------------------------------------
    |
    | Thresholds for the checks GET /health and app:doctor share. The disk
    | check degrades below the free-space floor, the schedule check degrades
    | when the heartbeat the scheduler writes every minute is older than the
    | window, and the queue check waits this long for a real job round-trip.
    |
    | This file is also spatie/laravel-health's config (it merges under the
    | same key, top-level keys here win). The checks run live on every request
    | and nothing reads stored results, so the in-memory store keeps the
    | package's own commands off the database table the package defaults to,
    | and notifications stay off. The merge is shallow: turning notifications
    | on means adding the package's other `notifications` keys here too.
    |
    */

    'result_stores' => [
        InMemoryHealthResultStore::class,
    ],

    'notifications' => [
        'enabled' => false,
    ],

    'disk_path' => storage_path(),

    'disk_minimum_free_percent' => 10.0,

    'schedule_max_age_minutes' => 10,

    'queue_timeout_seconds' => 2.0,

];
