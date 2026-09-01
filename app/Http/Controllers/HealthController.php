<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\HealthStatus;
use App\Support\Health\HealthReport;
use Illuminate\Http\JsonResponse;

/**
 * Unauthenticated and read by strangers: load balancers, uptime monitors,
 * anyone. The payload is statuses and durations only — never a version, a
 * config value, tenant data or an exception message.
 */
final class HealthController
{
    public function __invoke(HealthReport $report): JsonResponse
    {
        $result = $report->run();

        return new JsonResponse($result, $result['status'] === HealthStatus::Failed->value ? 503 : 200);
    }
}
