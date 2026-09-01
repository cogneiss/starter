<?php

declare(strict_types=1);

namespace App\Support\Health;

use App\Enums\HealthStatus;
use Throwable;

/**
 * Runs every registered check, times each one, and folds the results into a
 * single overall status: any Failed makes the whole report Failed, any
 * Degraded makes it Degraded, and everything green is Ok.
 */
final readonly class HealthReport
{
    /**
     * @param  list<Check>  $checks
     */
    public function __construct(private array $checks) {}

    /**
     * @return array{status: string, checks: list<array{name: string, status: string, duration_ms: float}>}
     */
    public function run(): array
    {
        $results = [];

        foreach ($this->checks as $check) {
            $start = hrtime(true);

            try {
                $status = $check->run();
            } catch (Throwable) {
                // The exception is dropped on purpose: /health is public, and
                // an exception message can carry a DSN, a path or a password.
                $status = HealthStatus::Failed;
            }

            $results[] = [
                'name' => $check->name(),
                'status' => $status->value,
                'duration_ms' => round((hrtime(true) - $start) / 1e6, 2),
            ];
        }

        $statuses = array_column($results, 'status');

        $overall = match (true) {
            in_array(HealthStatus::Failed->value, $statuses, true) => HealthStatus::Failed,
            in_array(HealthStatus::Degraded->value, $statuses, true) => HealthStatus::Degraded,
            default => HealthStatus::Ok,
        };

        return ['status' => $overall->value, 'checks' => $results];
    }
}
