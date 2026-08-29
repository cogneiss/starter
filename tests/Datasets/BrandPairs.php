<?php

declare(strict_types=1);

/**
 * The brand hex pairs every palette claim is tested against.
 *
 * One shared set, used by both the contrast property test and the test that
 * checks the set's own shape. Two sets would let a well-behaved one be shipped
 * for inspection while a friendlier one carried the assertions.
 *
 * The set is built rather than listed so it can be wide on purpose: a grey ramp
 * placed at each lightness decile, then a hue sweep across twelve hues, two
 * saturations and ten values, then the four colours that break naive palette
 * code — pure yellow, near-black, near-white and a fully desaturated grey.
 */
function brandSweepHexes(): array
{
    $hexes = [];

    // The grey ramp, one grey per lightness decile. OKLCH lightness of a grey
    // is the cube root of its linear channel, so the target inverts exactly.
    foreach (range(0, 9) as $decile) {
        $lightness = 0.05 + (0.1 * $decile);
        $linear = $lightness ** 3;
        $channel = $linear <= 0.0031308 ? 12.92 * $linear : (1.055 * $linear ** (1 / 2.4)) - 0.055;
        $byte = (int) round(255 * $channel);

        $hexes[] = sprintf('#%02X%02X%02X', $byte, $byte, $byte);
    }

    foreach (range(0, 11) as $step) {
        foreach ([0.35, 0.9] as $saturation) {
            foreach (range(1, 10) as $level) {
                $hexes[] = brandHexFromHsv($step * 30.0, $saturation, $level / 10);
            }
        }
    }

    return $hexes;
}

function brandHexFromHsv(float $hue, float $saturation, float $value): string
{
    $chroma = $value * $saturation;
    $sector = $hue / 60.0;
    $second = $chroma * (1 - abs(fmod($sector, 2.0) - 1));
    $match = $value - $chroma;

    $channels = match ((int) $sector) {
        0 => [$chroma, $second, 0.0],
        1 => [$second, $chroma, 0.0],
        2 => [0.0, $chroma, $second],
        3 => [0.0, $second, $chroma],
        4 => [$second, 0.0, $chroma],
        default => [$chroma, 0.0, $second],
    };

    return sprintf(
        '#%02X%02X%02X',
        ...array_map(static fn (float $channel): int => (int) round(255 * ($channel + $match)), $channels)
    );
}

/**
 * The set itself, so a test can assert the shape of the very entries the
 * dataset yields rather than of a copy that resembles them.
 *
 * @return array<string, array{string, string}>
 */
function brandPairs(): array
{
    $specials = [
        ['#FFFF00', '#3366FF'],
        ['#050505', '#FFFF00'],
        ['#FAFAFA', '#050505'],
        ['#808080', '#FAFAFA'],
        ['#3366FF', '#808080'],
    ];

    $hexes = brandSweepHexes();
    $count = count($hexes);

    $pairs = $specials;

    foreach ($hexes as $index => $hex) {
        $pairs[] = [$hex, $hexes[($index + 7) % $count]];
    }

    $unique = [];

    foreach ($pairs as [$primary, $accent]) {
        $unique[$primary.' + '.$accent] = [$primary, $accent];
    }

    return $unique;
}

dataset('brand pairs', brandPairs(...));
