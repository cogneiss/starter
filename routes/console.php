<?php

declare(strict_types=1);

use App\Models\LoginHistory;
use Illuminate\Support\Facades\Schedule;

Schedule::command('app:expire-feature-overrides')->daily();
Schedule::command('model:prune', ['--model' => [LoginHistory::class]])->daily();
Schedule::command('uploads:prune')->daily();
Schedule::command('tokens:prune')->daily();
Schedule::command('api:prune-logs')->daily();
