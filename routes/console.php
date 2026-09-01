<?php

declare(strict_types=1);

use App\Models\LoginHistory;
use App\Support\Health\Checks\ScheduleCheck;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schedule;

// The health endpoint's schedule check reads this timestamp; if the
// scheduler stops running, the heartbeat goes stale and the check degrades.
Schedule::call(fn () => Cache::put(ScheduleCheck::KEY, now()->getTimestamp()))
    ->everyMinute()
    ->name('health-heartbeat');

Schedule::command('app:expire-feature-overrides')->daily();
Schedule::command('model:prune', ['--model' => [LoginHistory::class]])->daily();
Schedule::command('uploads:prune')->daily();
Schedule::command('tokens:prune')->daily();
Schedule::command('api:prune-logs')->daily();
Schedule::command('audit:prune')->daily();
Schedule::command('gdpr:purge')->daily();
