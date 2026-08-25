<?php

declare(strict_types=1);

namespace App\Support;

use InvalidArgumentException;
use Laravel\Ai\Enums\Lab;

/**
 * Resolves a model tier from `config('ai.tiers')`.
 *
 * Agents that can express their preference with the SDK's `#[UseCheapestModel]`
 * and `#[UseSmartestModel]` attributes should. This is for the callers that
 * cannot — anonymous agents, mostly — so that a model name still never has to
 * be written into an agent class.
 */
final class AiTier
{
    /**
     * The provider and model configured for the given tier.
     *
     * A null model means the tier defers to the provider's own default.
     *
     * @return array{provider: Lab, model: string|null}
     */
    public static function for(string $tier): array
    {
        $config = config('ai.tiers.'.$tier);

        throw_unless(is_array($config), InvalidArgumentException::class, "There is no [{$tier}] model tier in config/ai.php.");

        $provider = $config['provider'] ?? null;
        $model = $config['model'] ?? null;

        throw_unless($provider instanceof Lab, InvalidArgumentException::class, "The [{$tier}] model tier has no provider.");

        return [
            'provider' => $provider,
            'model' => is_string($model) && $model !== '' ? $model : null,
        ];
    }
}
