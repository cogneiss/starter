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

    expect($decoded['checks'])->toHaveCount(12)
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
    config([
        'database.connections.broken' => ['driver' => 'sqlite', 'database' => '/nonexistent/directory/database.sqlite'],
        'database.default' => 'broken',
    ]);

    expect(Artisan::call('app:doctor', ['--json' => true]))->toBe(1);

    $output = Artisan::output();
    config(['database.default' => 'sqlite']);

    $checks = collect(json_decode($output, true, flags: JSON_THROW_ON_ERROR)['checks'])->keyBy('name');

    expect($checks['Database connection']['ok'])->toBeFalse()
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
