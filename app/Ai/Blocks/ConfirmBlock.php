<?php

declare(strict_types=1);

namespace App\Ai\Blocks;

use App\Enums\AiBlockType;
use App\Models\AiConfirmToken;
use Spatie\LaravelData\Attributes\Computed;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * What the person is asked to approve. Only the token id crosses the wire; the
 * summary and the expiry are read back off the token through the organization
 * global scope, so a block naming another organization's token finds nothing
 * and is dropped rather than rendered.
 */
#[TypeScript('AiConfirmBlock')]
final class ConfirmBlock extends Data implements AiBlock
{
    public AiBlockType $type = AiBlockType::Confirm;

    #[Computed]
    public string $summary;

    #[Computed]
    public string $expires_at;

    public function __construct(
        public string $token,
    ) {
        $confirmation = AiConfirmToken::query()->findOrFail($this->token);

        $this->summary = $confirmation->summary;
        $this->expires_at = $confirmation->expires_at->toIso8601String();
    }
}
