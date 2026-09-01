<?php

declare(strict_types=1);

namespace App\Data;

use App\Models\ApiToken;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * A token as the settings list shows it. The plaintext is not here and never
 * will be: it exists only in the flash message of the request that created it.
 */
#[TypeScript('ApiToken')]
final class ApiTokenData extends Data
{
    /**
     * @param  list<string>  $abilities
     */
    public function __construct(
        public string $id,
        public string $name,
        public array $abilities,
        public ?string $lastUsedAt,
        public ?string $expiresAt,
        public string $createdAt,
    ) {}

    public static function fromModel(ApiToken $token): self
    {
        return new self(
            id: $token->id,
            name: $token->name,
            abilities: $token->abilities ?? [],
            lastUsedAt: $token->last_used_at?->toIso8601String(),
            expiresAt: $token->expires_at?->toIso8601String(),
            createdAt: $token->created_at->toIso8601String(),
        );
    }
}
