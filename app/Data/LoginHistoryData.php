<?php

declare(strict_types=1);

namespace App\Data;

use App\Models\LoginHistory;
use App\Support\BrowserSession;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript('LoginHistoryEntry')]
final class LoginHistoryData extends Data
{
    public function __construct(
        public string $id,
        public string $device,
        public ?string $ip_address,
        public bool $successful,
        public string $created_at_diff,
    ) {}

    public static function fromModel(LoginHistory $login): self
    {
        return new self(
            id: $login->id,
            device: BrowserSession::device($login->user_agent),
            ip_address: $login->ip_address,
            successful: $login->successful,
            created_at_diff: $login->created_at->diffForHumans(),
        );
    }
}
