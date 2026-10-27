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

            $free = disk_free_space($path);
            $total = disk_total_space($path);

            if ($free === false || $total === false) {
                return HealthStatus::Degraded;
            }

            $percentFree = $free / $total * 100;

            return $percentFree >= config()->float('health.disk_minimum_free_percent')
                ? HealthStatus::Ok
                : HealthStatus::Degraded;
        }, HealthStatus::Degraded, report: false);
    }
}
