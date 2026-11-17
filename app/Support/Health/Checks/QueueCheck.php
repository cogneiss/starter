<?php

declare(strict_types=1);

namespace App\Support\Health\Checks;

use App\Jobs\QueueHeartbeat;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Sleep;
use Illuminate\Support\Str;
use Spatie\Health\Checks\Check;
use Spatie\Health\Checks\Result;

final class QueueCheck extends Check
{
    protected ?string $name = 'queue';

    /**
     * A real job round-trip: dispatch a heartbeat and wait for a worker to
     * mark it processed. The package's check reads a heartbeat a scheduled
     * command dispatched earlier, which answers a different question and
     * cannot answer inside one request.
     */
    public function run(): Result
    {
        return rescue(static function (): Result {
            $token = 'health:queue:'.Str::random(16);

            dispatch(new QueueHeartbeat($token));

            $deadline = microtime(true) + config()->float('health.queue_timeout_seconds');

            do {
                if (Cache::pull($token) === true) {
                    return Result::make()->ok();
                }

                Sleep::usleep(50_000);
            } while (microtime(true) < $deadline);

            return Result::make()->failed('The heartbeat job was not processed in time.');
        }, static fn (): Result => Result::make()->failed('The queue could not be reached.'), report: false);
    }
}
