<?php

declare(strict_types=1);

namespace App\Support;

/**
 * What the AI layer can actually do on this machine.
 *
 * A checkout with no provider keys is a supported state, not a broken one:
 * every agent answers from the fake gateway instead of throwing, so the
 * application boots, tests and demos without an account anywhere.
 */
final class AiAvailability
{
    /**
     * The names of the providers carrying a key. Names only — a key value
     * never leaves this class, so callers cannot leak one into a log or a
     * response.
     *
     * @return list<string>
     */
    public static function providers(): array
    {
        /** @var array<string, array{key?: mixed}> $providers */
        $providers = config('ai.providers', []);

        return array_keys(array_filter(
            $providers,
            static function (array $provider): bool {
                $key = $provider['key'] ?? null;

                return is_string($key) && $key !== '';
            },
        ));
    }

    /**
     * Whether a real provider could be reached at all.
     */
    public static function configured(): bool
    {
        return self::providers() !== [];
    }

    /**
     * Whether every agent is answered by the fake gateway rather than by a
     * provider — because nothing is configured, or because AI_FAKE pins it
     * that way on a machine that does have keys.
     */
    public static function faked(): bool
    {
        return config('ai.fake') === true || ! self::configured();
    }

    /**
     * The tier agents fall back to when they express no preference.
     */
    public static function defaultTier(): string
    {
        $tier = config('ai.default_tier');

        return is_string($tier) ? $tier : 'cheap';
    }
}
