<?php

declare(strict_types=1);

/**
 * The motion module is run, not read.
 *
 * A grep over the source would pass a module that names only `transform` while
 * computing a width somewhere else, so every named transition is asked what it
 * actually emits and the answer is checked: composited properties only, and a
 * duration that is really a duration.
 */
it('animates transform and opacity only, on every named transition', function (): void {
    $styles = motionStyles(reduced: false);

    expect($styles)->not->toBeEmpty();

    foreach ($styles as $style) {
        $properties = array_map(mb_trim(...), explode(',', $style['transitionProperty']));

        expect($properties)->each->toBeIn(['transform', 'opacity']);
        expect($style['transitionDuration'])->toMatch('/^\d+ms$/');
        expect((int) $style['transitionDuration'])->toBeGreaterThan(0);
    }
});
