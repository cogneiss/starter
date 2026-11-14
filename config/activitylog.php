<?php

declare(strict_types=1);

use App\Models\Activity;

/**
 * Only the values that differ from spatie/laravel-activitylog's defaults.
 * The audit trail itself is configured in config/audit.php.
 */
return [
    'activity_model' => Activity::class,
];
