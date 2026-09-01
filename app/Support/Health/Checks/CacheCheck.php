<?php

declare(strict_types=1);

namespace App\Support\Health\Checks;

use App\Enums\HealthStatus;
use App\Support\Health\Check;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

final class CacheCheck implements Check
{
    public function name(): string
    {
        return 'cache';
    }

    /**
     * A write followed by a read of the same value, so a store that accepts
     * writes but answers reads from nothing is caught, not just a dead server.
     */
    public function run(): HealthStatus
    {
        return rescue(static function (): HealthStatus {
            $token = Str::random(8);

            Cache::put('health:cache', $token, 10);

            return Cache::get('health:cache') === $token ? HealthStatus::Ok : HealthStatus::Failed;
        }, HealthStatus::Failed, report: false);
    }
}
