<?php

declare(strict_types=1);

use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;

/**
 * Points the application at a scratch checkout carrying the fresh-install
 * marker, so the command can really delete things without touching this repo.
 */
function freshInstall(): string
{
    $root = sys_get_temp_dir().'/install-command-'.uniqid();

    File::ensureDirectoryExists($root.'/app/Layer');
    File::put($root.'/app/Layer/One.php', 'one');
    File::put($root.'/self.txt', 'self');
    File::put($root.'/.starter-fresh', '');
    File::put($root.'/.env.example', "ORGANIZATIONS_RESOLVER=session\n");

    config(['install' => [
        'marker' => '.starter-fresh',
        'layers' => [
            'ai' => ['delete' => ['app/Layer'], 'composer' => ['laravel/ai']],
            'self' => ['delete' => ['self.txt']],
        ],
    ]]);

    app()->setBasePath($root);

    return $root;
}

afterEach(function (): void {
    app()->setBasePath(dirname(__DIR__, 3));
});

it('refuses to run against a checkout without the fresh-install marker', function (): void {
    $this->artisan('starter:install', ['--no-ai' => true])
        ->expectsOutputToContain('Not a fresh install')
        ->assertFailed();

    expect(is_dir(base_path('app/Ai')))->toBeTrue();
});

it('rejects an unknown tenancy mode', function (): void {
    $this->artisan('starter:install', ['--force' => true, '--tenancy' => 'galaxy'])
        ->expectsOutputToContain('--tenancy must be one of')
        ->assertFailed();
});

it('lists the steps of a dry run and changes nothing', function (): void {
    $root = freshInstall();

    $this->artisan('starter:install', ['--no-ai' => true, '--dry-run' => true, '--no-interaction' => true])
        ->expectsOutputToContain('[dry-run] delete app/Layer')
        ->assertSuccessful();

    expect(is_dir($root.'/app/Layer'))->toBeTrue()->and(is_file($root.'/.starter-fresh'))->toBeTrue();

    File::deleteDirectory($root);
});

it('keeps every layer by default and still removes itself and the marker', function (): void {
    $root = freshInstall();

    $this->artisan('starter:install', ['--no-interaction' => true])->assertSuccessful();

    expect(is_dir($root.'/app/Layer'))->toBeTrue()
        ->and(is_file($root.'/self.txt'))->toBeFalse()
        ->and(is_file($root.'/.starter-fresh'))->toBeFalse()
        ->and(File::get($root.'/.env.example'))->toContain('ORGANIZATIONS_RESOLVER=session');

    File::deleteDirectory($root);
});

it('removes the AI layer, sets the resolver and drops its package through composer', function (): void {
    $root = freshInstall();
    Process::fake();

    $this->artisan('starter:install', ['--no-ai' => true, '--tenancy' => 'single', '--no-interaction' => true])->assertSuccessful();

    expect(is_dir($root.'/app/Layer'))->toBeFalse()
        ->and(File::get($root.'/.env.example'))->toContain('ORGANIZATIONS_RESOLVER=single');

    Process::assertRan(fn ($process): bool => $process->command === ['composer', 'remove', 'laravel/ai', '--no-interaction']);

    File::deleteDirectory($root);
});

it('asks about the layers when it is interactive', function (): void {
    $root = freshInstall();

    $this->artisan('starter:install', ['--skip-composer' => true])
        ->expectsChoice('How do organizations resolve?', 'subdomain', ['session', 'subdomain', 'single'])
        ->expectsConfirmation('Keep the AI layer?', 'no')
        ->assertSuccessful();

    expect(is_dir($root.'/app/Layer'))->toBeFalse()
        ->and(File::get($root.'/.env.example'))->toContain('ORGANIZATIONS_RESOLVER=subdomain');

    File::deleteDirectory($root);
});
