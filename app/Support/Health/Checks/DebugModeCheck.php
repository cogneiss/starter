<?php

declare(strict_types=1);

namespace App\Support\Health\Checks;

use App\Enums\HealthStatus;
use App\Support\Health\Check;

final class DebugModeCheck implements Check
{
    public function name(): string
    {
        return 'debug-mode';
    }

    /**
     * APP_DEBUG in production prints stack traces, config and secrets to
     * strangers. Degraded rather than Failed: the application still serves,
     * it just serves too much.
     */
    public function run(): HealthStatus
    {
        return config()->boolean('app.debug') && config()->string('app.env') === 'production'
            ? HealthStatus::Degraded
            : HealthStatus::Ok;
    }
}
