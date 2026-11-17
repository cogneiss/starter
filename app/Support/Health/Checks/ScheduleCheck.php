<?php

declare(strict_types=1);

namespace App\Support\Health\Checks;

use Illuminate\Support\Facades\Cache;
use Spatie\Health\Checks\Check;
use Spatie\Health\Checks\Result;

final class ScheduleCheck extends Check
{
    /**
     * The cache key the scheduled heartbeat in routes/console.php stamps
     * every minute. Stale or missing means cron stopped calling in.
     */
    public const string KEY = 'health:schedule';

    protected ?string $name = 'schedule';

    /**
     * Stale is a warning, not the package's failure, so a fresh clone that
     * has not run its scheduler yet still answers 200.
     */
    public function run(): Result
    {
        $last = Cache::get(self::KEY);

        if (! is_int($last)) {
            return Result::make()->warning('The schedule has not run yet.');
        }

        return now()->getTimestamp() - $last <= config()->integer('health.schedule_max_age_minutes') * 60
            ? Result::make()->ok()
            : Result::make()->warning('The schedule heartbeat is stale.');
    }
}
