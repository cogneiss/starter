<?php

declare(strict_types=1);

namespace App\Data;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript('BrowserSession')]
final class BrowserSessionData extends Data
{
    public function __construct(
        public string $id,
        public string $device,
        public ?string $ip_address,
        public string $last_active_diff,
        public bool $current,
    ) {}
}
