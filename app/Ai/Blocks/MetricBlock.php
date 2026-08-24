<?php

declare(strict_types=1);

namespace App\Ai\Blocks;

use App\Enums\AiBlockType;
use App\Enums\AiMetricTrend;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript('AiMetricBlock')]
final class MetricBlock extends Data implements AiBlock
{
    public AiBlockType $type = AiBlockType::Metric;

    public function __construct(
        public string $label,
        public string $value,
        public ?string $delta = null,
        public ?AiMetricTrend $trend = null,
    ) {}
}
