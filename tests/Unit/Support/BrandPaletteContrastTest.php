<?php

declare(strict_types=1);

use App\Support\BrandPalette;

/**
 * The claim this file exists to keep: there is no pair of hexes a tenant can
 * pick that makes this application emit an unreadable palette. It is asserted
 * over the shared `brand pairs` set rather than over a handful of nice
 * colours, because the palettes that fail are never the nice ones.
 */
it('holds every emitted pair above its contrast ratio, in both modes', function (string $primary, string $accent): void {
    $failures = [];

    foreach (BrandPalette::from($primary, $accent) as $mode => $tokens) {
        foreach (BrandPalette::PAIRS as [$foreground, $background, $minimum]) {
            $ratio = BrandPalette::contrast($tokens[$foreground], $tokens[$background]);

            if ($ratio < $minimum) {
                $failures[] = sprintf(
                    '%s on %s in %s mode is %.2f:1, below the %.1f:1 it owes.',
                    $foreground,
                    $background,
                    $mode,
                    $ratio,
                    $minimum,
                );
            }
        }
    }

    expect($failures)->toBe([]);
})->with('brand pairs');
