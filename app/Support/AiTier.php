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
     * A null model means the tier defers to the provider's own default. When
     * the tier names a failover provider and both providers carry a key, the
     * provider is a `[name => model]` map, which the SDK walks left to right
     * and whose model argument it ignores. Otherwise it is the single primary
     * provider, which is the only shape a blank-key boot ever sees.
     *
     * @return array{provider: Lab|array<string, string|null>, model: string|null}
     */
    public static function for(string $tier): array
    {
        $config = config('ai.tiers.'.$tier);

        throw_unless(is_array($config), InvalidArgumentException::class, "There is no [{$tier}] model tier in config/ai.php.");

        $provider = $config['provider'] ?? null;

        throw_unless($provider instanceof Lab, InvalidArgumentException::class, "The [{$tier}] model tier has no provider.");

        $model = self::modelOf($config['model'] ?? null);
        $failover = $config['failover'] ?? null;

        if (
            is_array($failover)
            && ($failover['provider'] ?? null) instanceof Lab
            && array_diff([$provider->value, $failover['provider']->value], AiAvailability::providers()) === []
        ) {
            return [
                'provider' => [
                    $provider->value => $model,
                    $failover['provider']->value => self::modelOf($failover['model'] ?? null),
                ],
                'model' => null,
            ];
        }

        return ['provider' => $provider, 'model' => $model];
    }

    private static function modelOf(mixed $model): ?string
    {
        return is_string($model) && $model !== '' ? $model : null;
    }
}
