<?php

declare(strict_types=1);

namespace App\Support;

use Laravel\Ai\Responses\Data\Usage;

/**
 * Token usage to money, in integer micros. Prices live in `config('ai.pricing')`
 * as micros per million tokens, so 1_000_000 there means one dollar per million.
 *
 * A model nobody has priced costs zero. Guessing a price would put an invented
 * number in the ledger, which is worse than a gap in reporting.
 */
final class AiPricing
{
    public static function costMicros(?string $provider, ?string $model, Usage $usage): int
    {
        if ($provider === null || $model === null) {
            return 0;
        }

        $key = 'ai.pricing.'.$provider.'.'.$model;

        if (! is_array(config($key))) {
            return 0;
        }

        $input = config()->integer($key.'.input', 0);
        $output = config()->integer($key.'.output', 0);

        return intdiv($usage->promptTokens * $input, 1_000_000)
            + intdiv($usage->completionTokens * $output, 1_000_000);
    }
}
