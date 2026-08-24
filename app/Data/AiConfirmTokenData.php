<?php

declare(strict_types=1);

namespace App\Data;

use App\Models\AiConfirmToken;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * What the person is asked to confirm. The payload itself never crosses to the
 * client: the summary describes it, and the token id is what the confirm route
 * takes, so the browser cannot edit what will run.
 */
#[TypeScript('AiConfirmToken')]
final class AiConfirmTokenData extends Data
{
    public function __construct(
        public string $id,
        public string $action,
        public string $summary,
        public string $expires_at,
    ) {}

    public static function fromModel(AiConfirmToken $token): self
    {
        return new self(
            id: $token->id,
            action: $token->action,
            summary: $token->summary,
            expires_at: $token->expires_at->toIso8601String(),
        );
    }
}
