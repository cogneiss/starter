<?php

declare(strict_types=1);

namespace App\Ai\Blocks;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript('AiFormField')]
final class AiFormField extends Data
{
    public function __construct(
        public string $name,
        public string $value,
    ) {}
}
