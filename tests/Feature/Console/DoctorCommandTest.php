<?php

declare(strict_types=1);

use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Process;

/**
 * A healthy machine: bun answers, the types are already current.
 */
function fakeHealthyShell(): void
{
    Process::fake([
        'which bun' => Process::result(),
        '*typescript:transform' => Process::result(),
        'git status*' => Process::result(output: ''),
    ]);
}

/**
 * Swap in a documentation worklist for the duration of one assertion. The real
 * file is generated and git-ignored, but it belongs to whoever ran `wiki:audit`
 * last, so it goes back exactly as it was.
 */
function withAudit(?string $json, Closure $assert): void
{
    withWikiWorklistLock(function () use ($json, $assert): void {
        $path = base_path('wiki/_meta/audit.json');
        $original = is_file($path) ? (string) file_get_contents($path) : null;

        try {
            $json === null ? unlink($path) : file_put_contents($path, $json);

            $assert();
        } finally {
            $original === null ? unlink($path) : file_put_contents($path, $original);
        }
    });
}

beforeEach(function (): void {
    fakeHealthyShell();
});

it('passes every check on a working machine', function (): void {
    $this->artisan('app:doctor')
        ->assertExitCode(0)
        ->expectsOutputToContain('This machine is ready.');
})->skip(
    ! extension_loaded('xdebug') && ! extension_loaded('pcov'),
    'The coverage driver check fails without one loaded, which is the point of it.',
);

it('emits valid JSON', function (): void {
    Artisan::call('app:doctor', ['--json' => true]);

    $decoded = json_decode(Artisan::output(), true, flags: JSON_THROW_ON_ERROR);

    expect($decoded['checks'])->toHaveCount(13)
        ->and($decoded['checks'][0])->toHaveKeys(['name', 'ok', 'fix']);

    // Everything but the coverage driver, which depends on how PHP was invoked.
    $others = collect($decoded['checks'])->reject(fn (array $check): bool => $check['name'] === 'Coverage driver');

    expect($others->every(fn (array $check): bool => $check['ok']))->toBeTrue();
});

it('fails when the files a checkout needs are missing', function (): void {
    $files = Mockery::mock(Filesystem::class);
    $files->shouldReceive('get')->andReturn(file_get_contents(base_path('composer.json')));
    $files->shouldReceive('exists')->andReturnFalse();
    $files->shouldReceive('isDirectory')->andReturnFalse();

    $this->instance(Filesystem::class, $files);

    expect(Artisan::call('app:doctor', ['--json' => true]))->toBe(1);

    $checks = collect(json_decode(Artisan::output(), true, flags: JSON_THROW_ON_ERROR)['checks'])
        ->keyBy('name');

    expect($checks['.env file']['ok'])->toBeFalse()
        ->and($checks['node_modules']['fix'])->toBe('bun install')
        ->and($checks['Vite manifest']['ok'])->toBeFalse();
});

it('fails when the database cannot be reached', function (): void {
    // A connection that cannot open, left out of the way of the transaction
    // this test is already running in.
    $default = config('database.default');

    config([
        'database.connections.broken' => ['driver' => 'sqlite', 'database' => '/nonexistent/directory/database.sqlite'],
        'database.default' => 'broken',
    ]);

    expect(Artisan::call('app:doctor', ['--json' => true]))->toBe(1);

    $output = Artisan::output();
    config(['database.default' => $default]);

    $checks = collect(json_decode($output, true, flags: JSON_THROW_ON_ERROR)['checks'])->keyBy('name');

    expect($checks['Database connection (sqlite)']['ok'])->toBeFalse()
        // Nothing can be said about migrations without a database either.
        ->and($checks['Migrations']['ok'])->toBeFalse();
});

it('fails when a migration has not run', function (): void {
    DB::table('migrations')->orderByDesc('id')->limit(1)->delete();

    expect(Artisan::call('app:doctor', ['--json' => true]))->toBe(1);

    $checks = collect(json_decode(Artisan::output(), true, flags: JSON_THROW_ON_ERROR)['checks'])
        ->keyBy('name');

    expect($checks['Migrations']['ok'])->toBeFalse()
        ->and($checks['Migrations']['fix'])->toBe('php artisan migrate');
});

it('fails when bun is not installed', function (): void {
    Process::fake([
        'which bun' => Process::result(exitCode: 1),
        '*typescript:transform' => Process::result(),
        'git status*' => Process::result(output: ''),
    ]);

    $this->artisan('app:doctor')
        ->assertExitCode(1)
        ->expectsOutputToContain('bun on PATH')
        ->expectsOutputToContain('check(s) failed.');
});

it('fails when the generated TypeScript is stale', function (): void {
    Process::fake([
        'which bun' => Process::result(),
        '*typescript:transform' => Process::result(),
        'git status*' => Process::result(output: ' M resources/js/types/generated.d.ts'),
    ]);

    expect(Artisan::call('app:doctor', ['--json' => true]))->toBe(1);

    $checks = collect(json_decode(Artisan::output(), true, flags: JSON_THROW_ON_ERROR)['checks'])
        ->keyBy('name');

    expect($checks['Generated TypeScript']['ok'])->toBeFalse();
});

it('reports whether the database has pgvector installed', function (): void {
    Artisan::call('app:doctor', ['--json' => true]);

    $optional = collect(json_decode(Artisan::output(), true, flags: JSON_THROW_ON_ERROR)['optional'])
        ->keyBy('name');

    expect($optional['pgvector extension'])->toHaveKeys(['name', 'set', 'disables'])
        ->and($optional['pgvector extension']['disables'])->toBe('vector search is unavailable');
});

it('reports a missing third-party credential as off rather than failed', function (): void {
    config()->set('services.google.client_id', '');
    config()->set('services.github.client_id', '');
    config()->set('services.microsoft.client_id', '');

    Artisan::call('app:doctor', ['--json' => true]);

    $optional = collect(json_decode(Artisan::output(), true, flags: JSON_THROW_ON_ERROR)['optional'])
        ->keyBy('name');

    expect($optional['Social login']['set'])->toBeFalse()
        ->and($optional['Social login']['disables'])->toBe('the provider buttons stay hidden');
});

it('reports a configured third-party credential as set', function (): void {
    config()->set('services.google.client_id', 'a-client-id');

    Artisan::call('app:doctor', ['--json' => true]);

    $optional = collect(json_decode(Artisan::output(), true, flags: JSON_THROW_ON_ERROR)['optional'])
        ->keyBy('name');

    expect($optional['Social login']['set'])->toBeTrue();
});

it('points at /document when documentation work is outstanding', function (): void {
    withAudit(
        json_encode(['stale' => [['page' => 'a'], ['page' => 'b']], 'undocumented' => [['path' => 'c']], 'orphaned' => []]),
        function (): void {
            Artisan::call('app:doctor');

            expect(Artisan::output())->toContain('2 pages stale, 1 files undocumented');
        },
    );
});

it('says nothing about documentation when there is none to do', function (): void {
    withAudit(
        json_encode(['stale' => [], 'undocumented' => [], 'orphaned' => []]),
        function (): void {
            Artisan::call('app:doctor');

            expect(Artisan::output())->not->toContain('run /document');
        },
    );
});

it('says nothing about documentation when the worklist has never been generated', function (): void {
    withAudit(null, function (): void {
        Artisan::call('app:doctor');

        expect(Artisan::output())->not->toContain('run /document');
    });
});

it('treats an unreadable worklist as no work rather than crashing', function (): void {
    withAudit('not json', function (): void {
        Artisan::call('app:doctor');

        expect(Artisan::output())->not->toContain('run /document');
    });
});

/**
 * Zero keys is a reported state, never a failed check — a machine without an AI
 * account still runs, tests and builds the application.
 */
it('never fails a check over a missing AI provider key', function (): void {
    Artisan::call('app:doctor', ['--json' => true]);

    /** @var array{checks: list<array{name: string, ok: bool}>, optional: list<array{name: string, set: bool}>} $report */
    $report = json_decode(Artisan::output(), true, flags: JSON_THROW_ON_ERROR);

    $failed = array_column(array_filter($report['checks'], static fn (array $check): bool => ! $check['ok']), 'name');
    $reported = array_column(array_filter($report['optional'], static fn (array $entry): bool => str_starts_with($entry['name'], 'AI ')), 'set');

    expect($failed)->not->toContain('AI providers')
        ->and($reported)->toBe([false, false]);
});

it('reports the AI providers by name and the gateway, never a key value', function (): void {
    config()->set('ai.providers.anthropic.key', 'sk-ant-a-real-looking-secret');

    Artisan::call('app:doctor', ['--json' => true]);

    $output = Artisan::output();

    expect($output)->toContain('AI providers: anthropic')
        ->and($output)->toContain('AI live gateway (default tier: cheap)')
        ->and($output)->not->toContain('sk-ant');
});
