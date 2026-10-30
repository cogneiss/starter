<?php

declare(strict_types=1);

use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;

/**
 * The prune is only real if what is left still starts. A copy of this checkout
 * is pruned in a real process, then asked to boot and list its routes.
 */
it('prunes the AI layer and leaves an application that boots', function (): void {
    Process::preventStrayProcesses(false);

    $copy = sys_get_temp_dir().'/install-prune-'.getmypid();

    try {
        checkoutCopy($copy);
        File::put($copy.'/.starter-fresh', '');

        $install = Process::path($copy)->timeout(600)->run([
            PHP_BINARY, 'artisan', 'starter:install', '--no-ai', '--tenancy=single', '--skip-composer', '--no-interaction',
        ]);

        expect($install->successful())->toBeTrue($install->output().$install->errorOutput());

        foreach (['app/Ai', 'app/Mcp', 'config/ai.php', 'routes/ai.php', 'app/Models/AiMemory.php', '.starter-fresh', 'app/Console/Commands/StarterInstallCommand.php'] as $gone) {
            expect(file_exists($copy.'/'.$gone))->toBeFalse($gone);
        }

        expect(File::get($copy.'/.env.example'))->toContain('ORGANIZATIONS_RESOLVER=single')->not->toContain('OPENAI_API_KEY');

        $routes = Process::path($copy)->timeout(600)->run([PHP_BINARY, 'artisan', 'route:list', '--json']);

        expect($routes->successful())->toBeTrue($routes->output().$routes->errorOutput())
            ->and($routes->output())->not->toContain('ai-draft.store')
            ->and($routes->output())->toContain('organization.api-usage');
    } finally {
        File::deleteDirectory($copy);
    }
})->group('slow');
