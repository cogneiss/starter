<?php

declare(strict_types=1);

use App\Support\BrandPalette;

/**
 * A property test is only as good as the set it runs over. Two hundred colours
 * clustered around mid-tone blues would pass the contrast test while proving
 * nothing about the pale yellow that actually breaks a palette, so the set's
 * own shape is asserted here — over the same entries the contrast test uses.
 */
it('runs the contrast property over at least 200 distinct pairs', function (): void {
    $pairs = brandPairs();

    $distinct = array_unique(array_map(
        static fn (array $pair): string => $pair[0].'+'.$pair[1],
        $pairs,
    ));

    expect($distinct)->toHaveCount(count($pairs));
    expect($pairs)->toHaveCount(count($distinct));
    expect(count($pairs))->toBeGreaterThanOrEqual(200);
});

it('spans the lightness range, one entry per decile at least', function (): void {
    $deciles = [];

    foreach (brandPairs() as [$primary, $accent]) {
        foreach ([$primary, $accent] as $hex) {
            $deciles[min(9, (int) floor(BrandPalette::lightness($hex) * 10))] = true;
        }
    }

    $covered = array_keys($deciles);
    sort($covered);

    expect($covered)->toBe(range(0, 9));
});

it('includes the colours that break naive palettes', function (): void {
    $hexes = [];

    foreach (brandPairs() as [$primary, $accent]) {
        $hexes[] = $primary;
        $hexes[] = $accent;
    }

    expect($hexes)
        ->toContain('#FFFF00')  // pure yellow: bright and unreadable under white
        ->toContain('#050505')  // near-black
        ->toContain('#FAFAFA')  // near-white
        ->toContain('#808080'); // fully desaturated
});

it('asserts over the very entries the dataset yields', function (string $primary, string $accent): void {
    expect(brandPairs())->toHaveKey($primary.' + '.$accent, [$primary, $accent]);
})->with('brand pairs');
