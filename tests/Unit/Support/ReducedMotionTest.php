<?php

declare(strict_types=1);

/**
 * Reduced motion has to be instant, not merely quicker.
 *
 * A shortened animation is the failure this test exists to catch: it still
 * moves, and movement is exactly what the setting asks the interface to stop
 * doing. So the module is run with the preference on and every named transition
 * has to come back at zero.
 */
it('emits a zero duration for every named transition under reduced motion', function (): void {
    $styles = motionStyles(reduced: true);

    expect($styles)->not->toBeEmpty();

    foreach ($styles as $style) {
        expect($style['transitionDuration'])->toBe('0ms');
    }
});

it('still animates when reduced motion is not asked for', function (): void {
    $styles = motionStyles(reduced: false);

    expect(array_keys($styles))->toBe(array_keys(motionStyles(reduced: true)));

    foreach ($styles as $style) {
        expect($style['transitionDuration'])->not->toBe('0ms');
    }
});
