<?php

declare(strict_types=1);

namespace App\Support\Health;

use App\Enums\HealthStatus;

/**
 * One health probe, written once and read from two surfaces: the JSON that
 * GET /health serves and the lines app:doctor prints. A check reports a
 * HealthStatus and nothing else — no message ever reaches the public endpoint.
 */
interface Check
{
    public function name(): string;

    public function run(): HealthStatus;
}
