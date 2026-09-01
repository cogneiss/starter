<?php

declare(strict_types=1);

namespace App\Support\Health\Checks;

use App\Enums\HealthStatus;
use App\Support\Health\Check;

final class DiskCheck implements Check
{
    public function name(): string
    {
        return 'disk';
    }

    public function run(): HealthStatus
    {
        return rescue(static function (): HealthStatus {
            $path = config()->string('health.disk_path');

            $percentFree = disk_free_space($path) / disk_total_space($path) * 100;

            return $percentFree >= config()->float('health.disk_minimum_free_percent')
                ? HealthStatus::Ok
                : HealthStatus::Degraded;
        }, HealthStatus::Degraded, report: false);
    }
}
