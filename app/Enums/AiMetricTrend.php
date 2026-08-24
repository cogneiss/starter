<?php

declare(strict_types=1);

namespace App\Enums;

use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript('AiMetricTrend')]
enum AiMetricTrend: string
{
    case Up = 'up';
    case Down = 'down';
    case Flat = 'flat';
}
