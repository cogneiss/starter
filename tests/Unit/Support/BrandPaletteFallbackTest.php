<?php

declare(strict_types=1);

use App\Support\BrandPalette;

/**
 * @return array{float, float, float}
 */
function brandComponents(string $token): array
{
    preg_match('/^oklch\(([\d.]+) ([\d.]+) ([\d.]+)\)$/', $token, $matches);

    return [(float) $matches[1], (float) $matches[2], (float) $matches[3]];
}

it('drops a hue that cannot be made readable to the neutral ramp', function (): void {
    // A pale yellow. No lightness near it carries white text, and dragging it
    // far enough to work would leave the tenant with a colour they never chose.
    $palette = BrandPalette::from('#FFFFAA', '#3366FF');

    foreach (['primary', 'primary-hover', 'primary-active'] as $token) {
        [, $chroma] = brandComponents($palette['light'][$token]);

        expect($chroma)->toBe(0.0);
    }

    // Falling back is not the same as giving up: the neutral still reads.
    foreach ($palette as $tokens) {
        foreach (BrandPalette::PAIRS as [$foreground, $background, $minimum]) {
            expect(BrandPalette::contrast($tokens[$foreground], $tokens[$background]))
                ->toBeGreaterThanOrEqual($minimum);
        }
    }
});

it('keeps the hue of a colour that can be made readable', function (): void {
    $palette = BrandPalette::from('#3366FF', '#FF9900');

    foreach (['primary', 'accent'] as $token) {
        [, $chroma, $hue] = brandComponents($palette['light'][$token]);

        expect($chroma)->toBeGreaterThan(0.05);
        expect($hue)->toBeGreaterThan(0.0);
    }
});
