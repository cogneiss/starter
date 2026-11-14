<?php

declare(strict_types=1);

namespace App\Ai\Actions;

use Spatie\LaravelData\Attributes\Validation\Max;
use Spatie\LaravelData\Attributes\Validation\Required;
use Spatie\LaravelData\Data;

/**
 * The payload of a `remember-fact` proposal. The caps keep one confirmed fact
 * short enough to sit in a prompt.
 */
final class RememberFactData extends Data
{
    public function __construct(
        #[Required, Max(100)]
        public string $key,
        #[Required, Max(500)]
        public string $value,
    ) {}
}
