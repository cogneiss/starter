<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * What one check found. Failed means the application cannot serve requests
 * and /health answers 503; Degraded means it can, but somebody should look.
 */
enum HealthStatus: string
{
    case Ok = 'ok';
    case Degraded = 'degraded';
    case Failed = 'failed';
}
