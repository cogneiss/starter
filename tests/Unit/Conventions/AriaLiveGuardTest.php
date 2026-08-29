<?php

declare(strict_types=1);

use Symfony\Component\Finder\Finder;

/**
 * Two assertive regions on one page interrupt each other, and a screen reader
 * reads whichever won the race. So the interface has exactly one place allowed
 * to interrupt — the error region — and everything else announces politely and
 * waits its turn.
 *
 * The allowlist is a named list rather than a pattern on purpose: adding a
 * second interrupting region has to be a decision someone writes down here, not
 * something a wildcard quietly permits.
 */
const ASSERTIVE_ALLOWLIST = [
    'resources/js/components/alert-error.tsx',
];

it('lets only the allowlisted error region interrupt', function (): void {
    $offenders = [];

    foreach (Finder::create()->files()->in(base_path('resources/js'))->name(['*.ts', '*.tsx']) as $file) {
        if (! str_contains($file->getContents(), 'aria-live="assertive"')) {
            continue;
        }

        $path = str_replace(base_path().'/', '', $file->getPathname());

        if (! in_array($path, ASSERTIVE_ALLOWLIST, true)) {
            $offenders[] = $path;
        }
    }

    expect($offenders)->toBe([]);
});

it('keeps the allowlist honest', function (): void {
    foreach (ASSERTIVE_ALLOWLIST as $path) {
        expect(file_get_contents(base_path($path)))->toContain('aria-live="assertive"');
    }
});
