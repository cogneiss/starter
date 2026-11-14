<?php

declare(strict_types=1);

use App\Support\AiPricing;
use Laravel\Ai\Responses\Data\Usage;

it('prices every model a tier or its failover can resolve to', function (): void {
    $tiers = config()->array('ai.tiers');

    expect($tiers)->not->toBeEmpty();

    foreach ($tiers as $name => $tier) {
        foreach ([$tier, $tier['failover']] as $target) {
            $path = 'ai.pricing.'.$target['provider']->value.'.'.$target['model'];

            expect(config($path))->toBeArray("Tier [{$name}] resolves to {$path}, which has no price.")
                ->and(config($path.'.input'))->toBeInt()->toBeGreaterThan(0)
                ->and(config($path.'.output'))->toBeInt()->toBeGreaterThan(0);
        }
    }
});

it('prices the claude-opus-5-5 model', function (): void {
    expect(AiPricing::costMicros('anthropic', 'claude-opus-5-5', new Usage(1_000_000, 1_000_000)))->toBe(24_000_000);
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
