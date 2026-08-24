<?php

declare(strict_types=1);

use Symfony\Component\Process\Process;

/**
 * `.ai/guidelines/*.blade.php` is the source; these paths are outputs.
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
    '.cursor/rules/laravel-boost.mdc',
    '.junie/guidelines.md',
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

it('keeps GEMINI.md a copy of AGENTS.md', function (): void {
    expect(base_path('GEMINI.md'))->toBeFile();

    expect((string) file_get_contents(base_path('GEMINI.md')))
        ->toBe(
            (string) file_get_contents(base_path('AGENTS.md')),
            'GEMINI.md has drifted from AGENTS.md. Run: php -r "copy(\'AGENTS.md\', \'GEMINI.md\');"',
        );
});
