<?php

declare(strict_types=1);

namespace App\Support\Health;

use App\Enums\HealthStatus;
use Spatie\Health\Checks\Check;
use Spatie\Health\Health;
use Throwable;

/**
 * Runs every check registered on the package's Health registry, times each
 * one, and folds the results into a single overall status: any Failed makes
 * the whole report Failed, any Degraded makes it Degraded, and everything
 * green is Ok. It is the adapter between the package's Result and the shape
 * GET /health, the admin page and app:doctor read.
 */
final readonly class HealthReport
{
    public function __construct(private Health $health) {}

    /**
     * @return array{status: string, checks: list<array{name: string, status: string, duration_ms: float}>}
     */
    public function run(): array
    {
        $results = [];

        foreach ($this->health->registeredChecks() as $check) {
            $start = hrtime(true);

            $results[] = [
                'name' => $check->getName(),
                'status' => $this->statusOf($check)->value,
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

    /**
     * Anything the package reports other than ok or warning counts as failed,
     * so a crashed or skipped check can never read as green.
     */
    private function statusOf(Check $check): HealthStatus
    {
        try {
            return match ((string) $check->run()->status->value) {
                'ok' => HealthStatus::Ok,
                'warning' => HealthStatus::Degraded,
                default => HealthStatus::Failed,
            };
        } catch (Throwable) {
            // The exception is dropped on purpose: /health is public, and
            // an exception message can carry a DSN, a path or a password.
            return HealthStatus::Failed;
        }
    }
}
