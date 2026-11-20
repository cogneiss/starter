<?php

declare(strict_types=1);

use Symfony\Component\Process\Process as SymfonyProcess;

/**
 * `bin/prove-control.sh` proves that a test guards a control: it applies one
 * patch from `tests/Mutations` and expects the test to fail. A patch written
 * against an older version of its file no longer applies, and then the proof
 * cannot be run. The patches are applied by hand, so nothing else notices.
 *
 * Limit: this shows that a patch applies, not that its test still goes red.
 * `bin/prove-control.sh` decides that.
 */
it('keeps every mutation patch applicable to the current source', function (): void {
    $patches = glob(base_path('tests/Mutations/*.patch')) ?: [];
    $stale = [];

    foreach ($patches as $patch) {
        $check = new SymfonyProcess(['git', 'apply', '--check', $patch], base_path());

        if ($check->run() !== 0) {
            $stale[] = basename($patch).': '.mb_trim($check->getErrorOutput());
        }
    }

    expect($patches)->not->toBeEmpty()
        ->and($stale)->toBeEmpty(implode(PHP_EOL, $stale));
});
