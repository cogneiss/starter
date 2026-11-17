<?php

declare(strict_types=1);

use Symfony\Component\Process\Process;

/**
 * `.ai/guidelines/*.blade.php` is the source and `AGENTS.md` is the output.
 * `CLAUDE.md` is a one-line import of it. It sits in the snapshot so a render
 * that writes the block into it again counts as drift.
 *
 * The render happens in a subprocess for two reasons: Boost disables itself
 * while the test suite is running, so `boost:install` is not registered in
 * this process, and running the same command a developer runs is the only
 * comparison worth making. It renders in place, compares against a snapshot,
 * then puts the snapshot back — a red run leaves the working tree exactly as
 * it found it, so the gate reports drift rather than quietly fixing it.
 */
$generated = [
    'AGENTS.md',
    'CLAUDE.md',
];

it('has the generated agent guideline files in sync with their source', function () use ($generated): void {
    $before = [];

    foreach ($generated as $path) {
        expect(base_path($path))->toBeFile();
        $before[$path] = (string) file_get_contents(base_path($path));
    }

    try {
        // Not the Process facade: tests/Pest.php prevents stray processes, and
        // this one is not stray — the render is what the test is about.
        $render = new Process(
            ['php', 'artisan', 'boost:install', '--guidelines', '--skills', '--no-interaction'],
            base_path(),
            ['APP_ENV' => 'local'],
        );

        $render->run();

        $drifted = array_values(array_filter(
            $generated,
            fn (string $path): bool => (string) file_get_contents(base_path($path)) !== $before[$path],
        ));
    } finally {
        foreach ($before as $path => $contents) {
            file_put_contents(base_path($path), $contents);
        }
    }

    expect($render->isSuccessful())->toBeTrue('boost:install failed: '.$render->getErrorOutput());

    expect($drifted)->toBe([], sprintf(
        'These generated files are out of date with .ai/guidelines: %s. Run: php artisan boost:install --guidelines --skills --no-interaction',
        implode(', ', $drifted),
    ));
});

it('keeps CLAUDE.md a one-line import of AGENTS.md', function (): void {
    expect(base_path('CLAUDE.md'))->toBeFile();

    expect((string) file_get_contents(base_path('CLAUDE.md')))
        ->toBe("@AGENTS.md\n", 'CLAUDE.md holds only the line @AGENTS.md. The guidelines live in AGENTS.md.');
});
