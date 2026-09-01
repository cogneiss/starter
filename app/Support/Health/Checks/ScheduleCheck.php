<?php

declare(strict_types=1);

namespace App\Support\Health\Checks;

use App\Enums\HealthStatus;
use App\Support\Health\Check;
use Illuminate\Support\Facades\Cache;

final class ScheduleCheck implements Check
{
    /**
     * The cache key the scheduled heartbeat in routes/console.php stamps
     * every minute. Stale or missing means cron stopped calling in.
     */
    public const string KEY = 'health:schedule';

    public function name(): string
    {
        return 'schedule';
    }

    public function run(): HealthStatus
    {
        $last = Cache::get(self::KEY);

        if (! is_int($last)) {
            return HealthStatus::Degraded;
        }

        return now()->getTimestamp() - $last <= config()->integer('health.schedule_max_age_minutes') * 60
            ? HealthStatus::Ok
            : HealthStatus::Degraded;
    }
}
