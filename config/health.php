<?php

declare(strict_types=1);

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
    */

    'disk_path' => storage_path(),

    'disk_minimum_free_percent' => 10.0,

    'schedule_max_age_minutes' => 10,

    'queue_timeout_seconds' => 2.0,

];
