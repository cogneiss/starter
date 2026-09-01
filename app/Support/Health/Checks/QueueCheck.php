<?php

declare(strict_types=1);

namespace App\Support\Health\Checks;

use App\Enums\HealthStatus;
use App\Jobs\QueueHeartbeat;
use App\Support\Health\Check;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Sleep;
use Illuminate\Support\Str;

final class QueueCheck implements Check
{
    public function name(): string
    {
        return 'queue';
    }

    /**
     * A real job round-trip: dispatch a heartbeat and wait for a worker to
     * mark it processed. A connection-string ping would pass with every
     * worker dead, which is exactly the outage this check exists to see.
     */
    public function run(): HealthStatus
    {
        return rescue(static function (): HealthStatus {
            $token = 'health:queue:'.Str::random(16);

            QueueHeartbeat::dispatch($token);

            $deadline = microtime(true) + config()->float('health.queue_timeout_seconds');

            do {
                if (Cache::pull($token) === true) {
                    return HealthStatus::Ok;
                }

                Sleep::usleep(50_000);
            } while (microtime(true) < $deadline);

            return HealthStatus::Failed;
        }, HealthStatus::Failed, report: false);
    }
}
