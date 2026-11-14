<?php

declare(strict_types=1);

namespace App\Support;

use Laravel\Ai\Responses\Data\Usage;

/**
 * Token usage to money, in integer micros. Prices live in `config('ai.pricing')`
 * as micros per million tokens, so 1_000_000 there means one dollar per million.
 *
 * A model nobody has priced is billed at `config('ai.pricing_fallback')`, a
 * ceiling. Charging zero would leave the spend unmetered and the budget
 * unenforced, and the ceiling never throws, so a blank-key boot is unaffected.
 */
final class AiPricing
{
    public static function costMicros(?string $provider, ?string $model, Usage $usage): int
    {
        $key = $provider === null || $model === null ? 'ai.pricing_fallback' : "ai.pricing.{$provider}.{$model}";

        if (! is_array(config($key))) {
            $key = 'ai.pricing_fallback';
        }

        $input = config()->integer($key.'.input', 0);
        $output = config()->integer($key.'.output', 0);

        return intdiv($usage->inputTokens * $input, 1_000_000)
            + intdiv($usage->outputTokens * $output, 1_000_000);
    }
}
