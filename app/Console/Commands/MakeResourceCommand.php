<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Str;

#[Description('Scaffold a resource: model, migration, factory, data object, policy, action, request, controller, adapter, page and tests')]
#[Signature('app:make-resource
    {name : The singular studly name of the resource, e.g. Project}
    {--no-migration : Skip the migration}
    {--force : Overwrite files that already exist}
    {--dry-run : List what would be written without writing anything}
    {--base= : Generate into this directory instead of the application root}')]
final class MakeResourceCommand extends Command
{
    public function handle(Filesystem $files): int
    {
        $name = Str::studly((string) $this->argument('name'));

        if (in_array(preg_match('/^[A-Za-z]+$/', $name), [0, false], true)) {
            $this->components->error('The resource name has to be alphabetic, e.g. Project or BlogPost.');

            return self::FAILURE;
        }

        $replacements = $this->replacements($name);
        $base = mb_rtrim($this->option('base') ?? $this->laravel->basePath(), '/');
        $targets = $this->targets($replacements);

        $clashes = array_values(array_filter(
            array_keys($targets),
            fn (string $path): bool => $files->exists($base.'/'.$path),
        ));

        if ($clashes !== [] && $this->option('force') === false) {
            $this->components->error('These files already exist. Re-run with --force to overwrite them:');
            $this->components->bulletList($clashes);

            return self::FAILURE;
        }

        if ($this->option('dry-run') === true) {
            $this->components->info('Would write:');
            $this->components->bulletList(array_keys($targets));

            return self::SUCCESS;
        }

        foreach ($targets as $path => $stub) {
            $files->ensureDirectoryExists(dirname($base.'/'.$path));
            $files->put($base.'/'.$path, $this->render($files, $stub, $replacements));
        }

        $this->components->bulletList(array_keys($targets));

        $this->registerRoutes($files, $base, $replacements);
        $this->registerPermission($files, $base, $replacements);

        $this->format($base, $targets);

        $this->components->info(sprintf('%s is ready.', $replacements['{{ title }}']));
        $this->components->bulletList([
            'Add your own columns to the migration, then run `php artisan migrate`.',
            sprintf('Add the matching fields to %sData and to the create page.', $replacements['{{ class }}']),
            sprintf('The routes are registered as %s.create and %s.store — move them if you want a different URL.', $replacements['{{ kebab }}'], $replacements['{{ kebab }}']),
            'Run `composer typescript:generate` after changing the data object.',
        ]);

        return self::SUCCESS;
    }

    /**
     * @return array<string, string>
     */
    private function replacements(string $name): array
    {
        $plural = Str::plural($name);
        $sentence = fn (string $value): string => Str::ucfirst(Str::lower(Str::headline($value)));

        return [
            '{{ class }}' => $name,
            '{{ variable }}' => Str::camel($name),
            '{{ table }}' => Str::snake($plural),
            '{{ key }}' => Str::kebab($plural),
            '{{ kebab }}' => Str::kebab($name),
            '{{ label }}' => $sentence($plural),
            '{{ title }}' => $sentence($name),
            '{{ lower }}' => Str::lower(Str::headline($name)),
            '{{ permission }}' => Str::lower($plural),
            '{{ namespace }}' => mb_rtrim($this->laravel->getNamespace(), '\\'),
        ];
    }

    /**
     * Relative path to the stub that fills it.
     *
     * @param  array<string, string>  $replacements
     * @return array<string, string>
     */
    private function targets(array $replacements): array
    {
        $class = $replacements['{{ class }}'];

        $targets = ['app/Models/'.$class.'.php' => 'model'];

        if ($this->option('no-migration') === false) {
            $targets['database/migrations/'.now()->format('Y_m_d_His').'_create_'.$replacements['{{ table }}'].'_table.php'] = 'migration';
        }

        return $targets + [
            'database/factories/'.$class.'Factory.php' => 'factory',
            'app/Data/'.$class.'Data.php' => 'data',
            'app/Policies/'.$class.'Policy.php' => 'policy',
            'app/Actions/Create'.$class.'.php' => 'action',
            'app/Http/Requests/Create'.$class.'Request.php' => 'request',
            'app/Http/Controllers/'.$class.'Controller.php' => 'controller',
            'app/Resources/Definitions/'.$class.'Resource.php' => 'resource',
            'resources/js/pages/'.$replacements['{{ kebab }}'].'/create.tsx' => 'page',
            'tests/Feature/Controllers/'.$class.'ControllerTest.php' => 'controller-test',
            'tests/Unit/Actions/Create'.$class.'Test.php' => 'action-test',
            'tests/Unit/Models/'.$class.'Test.php' => 'model-test',
            'tests/Unit/Data/'.$class.'DataTest.php' => 'data-test',
        ];
    }

    /**
     * @param  array<string, string>  $replacements
     */
    private function render(Filesystem $files, string $stub, array $replacements): string
    {
        return str_replace(
            array_keys($replacements),
            array_values($replacements),
            $files->get($this->laravel->basePath('stubs/resource/'.$stub.'.stub')),
        );
    }

    /**
     * @param  array<string, string>  $replacements
     */
    private function registerRoutes(Filesystem $files, string $base, array $replacements): void
    {
        $path = $base.'/routes/web.php';
        $block = $this->render($files, 'routes', $replacements);
        $contents = $files->get($path);

        if (str_contains($contents, $block)) {
            return;
        }

        $files->put($path, $this->withImport($contents, 'use App\Http\Controllers\\'.$replacements['{{ class }}'].'Controller;').$block);
    }

    /**
     * Slot the controller import in with the other controller imports so the
     * file stays alphabetical without waiting for the formatter.
     */
    private function withImport(string $contents, string $import): string
    {
        $lines = explode("\n", $contents);
        $controllers = array_keys(array_filter(
            $lines,
            static fn (string $line): bool => str_starts_with($line, 'use App\Http\Controllers\\'),
        ));

        if ($controllers === []) {
            return $contents;
        }

        $after = array_filter($controllers, static fn (int $line): bool => $lines[$line] > $import);
        $at = $after === [] ? max($controllers) + 1 : min($after);

        array_splice($lines, $at, 0, [$import]);

        return implode("\n", $lines);
    }

    /**
     * @param  array<string, string>  $replacements
     */
    private function registerPermission(Filesystem $files, string $base, array $replacements): void
    {
        $path = $base.'/app/Support/PermissionCatalog.php';
        $contents = $files->get($path);
        $definition = $this->render($files, 'permission', $replacements);
        $closing = '        ];';

        if (str_contains($contents, $definition) || ! str_contains($contents, $closing)) {
            return;
        }

        $at = (int) mb_strpos($contents, $closing);

        $files->put($path, mb_substr($contents, 0, $at).$definition.mb_substr($contents, $at));
    }

    /**
     * Hand the generated files to the project's own formatters, so the very
     * first thing a developer runs — `composer test` — is already green.
     *
     * @param  array<string, string>  $targets
     */
    private function format(string $base, array $targets): void
    {
        $php = array_values(array_filter(
            array_keys($targets),
            static fn (string $path): bool => str_ends_with($path, '.php'),
        ));

        Process::path($base)->run([
            'vendor/bin/pint', '--quiet', 'routes/web.php', 'app/Support/PermissionCatalog.php', ...$php,
        ]);

        Process::path($base)->run([PHP_BINARY, 'artisan', 'wayfinder:generate', '--with-form']);
        Process::path($base)->run([PHP_BINARY, 'artisan', 'typescript:transform']);

        // Last, so it also picks up what the two generators just wrote.
        Process::path($base)->env(['NODE_OPTIONS' => '--experimental-strip-types'])->run([
            'node_modules/.bin/vp', 'fmt', 'resources/js/pages', 'resources/js/types',
        ]);
    }
}
