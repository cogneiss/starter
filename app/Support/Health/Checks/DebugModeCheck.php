<?php

declare(strict_types=1);

namespace App\Support\Health\Checks;

use Spatie\Health\Checks\Check;
use Spatie\Health\Checks\Result;

final class DebugModeCheck extends Check
{
    protected ?string $name = 'debug-mode';

    /**
     * APP_DEBUG in production prints stack traces, config and secrets to
     * strangers. A warning rather than a failure: the application still
     * serves, it just serves too much. The package's own check fails on any
     * mismatch in any environment, which would 503 every local machine.
     */
    public function run(): Result
    {
        return config()->boolean('app.debug') && config()->string('app.env') === 'production'
            ? Result::make()->warning('APP_DEBUG is on in production.')
            : Result::make()->ok();
    }
}
