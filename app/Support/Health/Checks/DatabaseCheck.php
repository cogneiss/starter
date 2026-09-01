<?php

declare(strict_types=1);

namespace App\Support\Health\Checks;

use App\Enums\HealthStatus;
use App\Support\Health\Check;
use Illuminate\Support\Facades\DB;

final class DatabaseCheck implements Check
{
    public function name(): string
    {
        return 'database';
    }

    public function run(): HealthStatus
    {
        return rescue(static function (): HealthStatus {
            DB::connection()->select('select 1');

            return HealthStatus::Ok;
        }, HealthStatus::Failed, report: false);
    }
}
