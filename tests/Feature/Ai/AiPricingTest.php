<?php

declare(strict_types=1);

use App\Support\AiPricing;
use Laravel\Ai\Responses\Data\TextUsage;
use Laravel\Ai\Responses\Data\Usage;

it('prices every model a tier or its failover can resolve to', function (): void {
    $tiers = config()->array('ai.tiers');

    expect($tiers)->not->toBeEmpty();

    foreach ($tiers as $name => $tier) {
        foreach ([$tier, $tier['failover']] as $target) {
            $price = AiPricing::listed($target['provider']->value, $target['model']);

            expect($price)->toBeArray("Tier [{$name}] resolves to {$target['model']}, which has no price.")
                ->and($price['input'] ?? null)->toBeInt()->toBeGreaterThan(0)
                ->and($price['output'] ?? null)->toBeInt()->toBeGreaterThan(0);
        }
    }
});

it('prices the claude-opus-5-5 model', function (): void {
    expect(AiPricing::costMicros('anthropic', 'claude-opus-5-5', new Usage(1_000_000, 1_000_000)))->toBe(24_000_000);
});

it('prices a model whose name has a dot in it', function (): void {
    expect(AiPricing::costMicros('openai', 'gpt-6.1-terra', new Usage(1_000_000, 1_000_000)))->toBe(6_000_000);
});

it('keeps the fallback at or above every listed price', function (): void {
    $fallback = config()->array('ai.pricing_fallback');

    foreach (config()->array('ai.pricing') as $models) {
        foreach ($models as $price) {
            expect($fallback['input'])->toBeGreaterThanOrEqual($price['input'])
                ->and($fallback['output'])->toBeGreaterThanOrEqual($price['output']);
        }
    }
});

it('bills a dated snapshot id at the base model price in both date formats', function (): void {
    $usage = new Usage(1_000_000, 1_000_000);

    expect(AiPricing::costMicros('anthropic', 'claude-opus-5-5-20261001', $usage))->toBe(24_000_000)
        ->and(AiPricing::costMicros('anthropic', 'claude-opus-5-5-2026-10-01', $usage))->toBe(24_000_000);
});

it('bills the ceiling for a suffix that is not a date', function (): void {
    $usage = new Usage(1_000_000, 1_000_000);

    expect(AiPricing::costMicros('anthropic', 'claude-opus-5-5-fast', $usage))->toBe(60_000_000)
        ->and(AiPricing::costMicros('anthropic', 'claude-opus-5-5-2026100', $usage))->toBe(60_000_000)
        ->and(AiPricing::listed('anthropic', 'claude-opus-5-5-fast'))->toBeNull();
});

it('treats a price row without an integer input and output as unlisted', function (): void {
    config()->set('ai.pricing.anthropic.claude-broken-a', ['output' => 1]);
    config()->set('ai.pricing.anthropic.claude-broken-b', ['input' => 1]);
    config()->set('ai.pricing.anthropic.claude-broken-c', ['input' => '1', 'output' => 1]);
    config()->set('ai.pricing.anthropic.claude-broken-d', 'free');

    $usage = new Usage(1_000_000, 1_000_000);

    foreach (['a', 'b', 'c', 'd'] as $suffix) {
        expect(AiPricing::listed('anthropic', "claude-broken-{$suffix}"))->toBeNull()
            ->and(AiPricing::costMicros('anthropic', "claude-broken-{$suffix}", $usage))->toBe(60_000_000);
    }
});

it('bills cache reads and writes at their own rates and carves them out of the input', function (): void {
    $usage = new TextUsage(1_000_000, 0, 600_000, 200_000);

    expect(AiPricing::costMicros('anthropic', 'claude-sonnet-5-5', $usage))->toBe(400_000 + 60_000 + 500_000)
        ->and(AiPricing::costMicros('anthropic', 'claude-sonnet-5-5', new Usage(1_000_000, 0)))->toBe(2_000_000);
});

it('bills cached tokens at the input rate when the row has no cache prices', function (): void {
    $usage = new TextUsage(1_000_000, 0, 600_000, 200_000);

    expect(AiPricing::costMicros('openai', 'gpt-6-luna', $usage))->toBe(100_000)
        ->and(AiPricing::costMicros('openai', 'unlisted', $usage))->toBe(10_000_000);
});

it('never bills more cache tokens than the prompt held', function (): void {
    $usage = new TextUsage(1_000_000, 0, 5_000_000, 5_000_000);

    expect(AiPricing::costMicros('anthropic', 'claude-sonnet-5-5', $usage))->toBe(100_000)
        ->and(AiPricing::costMicros('anthropic', 'claude-sonnet-5-5', new TextUsage(1_000_000, 0, -5, -5)))->toBe(2_000_000);
});

it('meters long-context runs at the higher tier', function (): void {
    $below = new Usage(100_000, 1_000_000);
    $above = new Usage(100_001, 1_000_000);

    expect(AiPricing::costMicros('anthropic', 'claude-haiku-5-5', $below))->toBe(10_000 + 500_000)
        ->and(AiPricing::costMicros('anthropic', 'claude-haiku-5-5', $above))->toBe(50_000 + 2_500_000);
});

it('bills the cache of a long-context run at the tier rates, else the tier input', function (): void {
    $usage = new TextUsage(1_000_000, 0, 1_000_000, 0);

    expect(AiPricing::costMicros('anthropic', 'claude-haiku-5-5', $usage))->toBe(500_000);

    config()->set('ai.pricing.anthropic.claude-haiku-5-5.long_context.cache_read', 50_000);

    expect(AiPricing::costMicros('anthropic', 'claude-haiku-5-5', $usage))->toBe(50_000);
});

it('ignores malformed optional price keys instead of throwing', function (): void {
    config()->set('ai.pricing.anthropic.claude-odd', [
        'input' => 1_000_000,
        'output' => 1_000_000,
        'cache_read' => 'cheap',
        'long_context' => ['above' => 10, 'input' => '5', 'output' => 5],
    ]);

    expect(AiPricing::listed('anthropic', 'claude-odd'))->toBe(['input' => 1_000_000, 'output' => 1_000_000])
        ->and(AiPricing::costMicros('anthropic', 'claude-odd', new TextUsage(1_000, 1_000, 500, 0)))->toBe(2_000);
});
