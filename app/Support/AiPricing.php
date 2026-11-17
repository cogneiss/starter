<?php

declare(strict_types=1);

namespace App\Support;

use Laravel\Ai\Responses\Data\TextUsage;
use Laravel\Ai\Responses\Data\Usage;

/**
 * Token usage to money, in integer micros. Prices live in `config('ai.pricing')`
 * as micros per million tokens, so 1_000_000 there means one dollar per million.
 *
 * A model nobody has priced is billed at `config('ai.pricing_fallback')`, a
 * ceiling. Charging zero would leave the spend unmetered and the budget
 * unenforced, and the ceiling never throws, so a blank-key boot is unaffected.
 *
 * A provider may return a dated snapshot id such as `claude-sonnet-5-5-20261001`
 * for a model listed as `claude-sonnet-5-5`. An exact match wins; on a miss one
 * trailing date suffix is stripped and the lookup retried once. Any other
 * suffix stays unlisted. A price row without an integer `input` and `output` is
 * treated as unlisted too, so a malformed row bills the ceiling instead of
 * throwing after the provider call was already paid for.
 *
 * Both providers report `inputTokens` as the whole prompt, cache buckets
 * included (`TextUsage::uncachedInputTokens()`), so cache tokens are carved out
 * of it rather than added: the uncached remainder bills at `input`, cache reads
 * at `cache_read` and cache writes at `cache_write`. A row without those keys
 * bills cached tokens at `input`, which overstates and never understates.
 *
 * A row may carry `long_context => [above, input, output]`. A request whose
 * whole prompt exceeds `above` tokens is billed at that tier's rates in full,
 * the way the provider bills it. Its cache tokens bill at the tier's own
 * `cache_read` / `cache_write` or, lacking them, at the tier's `input`.
 */
final class AiPricing
{
    private const string SNAPSHOT_SUFFIX = '/-(\d{8}|\d{4}-\d{2}-\d{2})$/';

    public static function costMicros(?string $provider, ?string $model, Usage $usage): int
    {
        /** @var array{input: int, output: int} $fallback */
        $fallback = config()->array('ai.pricing_fallback');

        $price = self::listed($provider, $model) ?? $fallback;
        $longContext = $price['long_context'] ?? null;

        if ($longContext !== null && $usage->inputTokens > $longContext['above']) {
            $price = [...$longContext, 'cache_read' => $longContext['cache_read'] ?? $longContext['input'], 'cache_write' => $longContext['cache_write'] ?? $longContext['input']];
        }

        $read = $usage instanceof TextUsage ? min(max($usage->cacheReadInputTokens ?? 0, 0), $usage->inputTokens) : 0;
        $write = $usage instanceof TextUsage ? min(max($usage->cacheWriteInputTokens ?? 0, 0), $usage->inputTokens - $read) : 0;

        return intdiv(($usage->inputTokens - $read - $write) * $price['input'], 1_000_000)
            + intdiv($read * ($price['cache_read'] ?? $price['input']), 1_000_000)
            + intdiv($write * ($price['cache_write'] ?? $price['input']), 1_000_000)
            + intdiv($usage->outputTokens * $price['output'], 1_000_000);
    }

    /**
     * The listed price of a model, or null when nobody has priced it. Read by
     * array key and never by dot notation: a model name such as `gpt-6.1-terra`
     * has a dot in it, and `config('ai.pricing.openai.gpt-6.1-terra')` would
     * look for a `gpt-6` entry and silently bill the ceiling.
     *
     * Null covers an unlisted model, a suffix that is not a date, and a row
     * whose `input` or `output` is not an integer. The optional `cache_read`
     * and `cache_write` keys and a `long_context` tier are kept only when
     * well formed; a malformed optional key is dropped, never thrown on.
     *
     * @return array{input: int, output: int, cache_read?: int, cache_write?: int, long_context?: array{above: int, input: int, output: int, cache_read?: int, cache_write?: int}}|null
     */
    public static function listed(?string $provider, ?string $model): ?array
    {
        /** @var array<string, array<string, mixed>> $prices */
        $prices = config()->array('ai.pricing');

        $models = $prices[$provider ?? ''] ?? [];
        $name = $model ?? '';
        $row = $models[$name] ?? $models[preg_replace(self::SNAPSHOT_SUFFIX, '', $name) ?? $name] ?? null;

        $input = is_array($row) ? ($row['input'] ?? null) : null;
        $output = is_array($row) ? ($row['output'] ?? null) : null;

        if (! is_int($input) || ! is_int($output)) {
            return null;
        }

        $price = ['input' => $input, 'output' => $output, ...self::cacheRates($row)];
        $tier = $row['long_context'] ?? null;
        $above = is_array($tier) ? ($tier['above'] ?? null) : null;
        $tierInput = is_array($tier) ? ($tier['input'] ?? null) : null;
        $tierOutput = is_array($tier) ? ($tier['output'] ?? null) : null;

        if (is_int($above) && is_int($tierInput) && is_int($tierOutput)) {
            $price['long_context'] = ['above' => $above, 'input' => $tierInput, 'output' => $tierOutput, ...self::cacheRates($tier)];
        }

        return $price;
    }

    /**
     * @param  array<array-key, mixed>  $row
     * @return array{cache_read?: int, cache_write?: int}
     */
    private static function cacheRates(array $row): array
    {
        return array_filter(
            ['cache_read' => $row['cache_read'] ?? null, 'cache_write' => $row['cache_write'] ?? null],
            is_int(...),
        );
    }
}
