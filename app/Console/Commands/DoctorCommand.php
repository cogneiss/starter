<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Database\Migrations\Migrator;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Process;

/**
 * Every question a fresh checkout asks, answered in one command.
 *
 * Each check is a boolean expression and a remediation line, so a failure tells
 * you what to run rather than what is wrong.
 */
#[Description('Check that this machine can run, test and build the application')]
#[Signature('app:doctor {--json : Emit the results as JSON}')]
final class DoctorCommand extends Command
{
    private const array REQUIRED_EXTENSIONS = ['pdo_sqlite', 'mbstring', 'openssl', 'intl'];

    private const string GENERATED_TYPES = 'resources/js/types/generated.d.ts';

    public function handle(Filesystem $files, Migrator $migrator): int
    {
        $checks = [
            $this->php($files),
            $this->extensions(),
            $this->coverageDriver(),
            $this->environmentFile($files),
            $this->applicationKey(),
            $this->database(),
            $this->migrations($migrator),
            $this->bun(),
            $this->frontendDependencies($files),
            $this->viteManifest($files),
            $this->generatedTypeScript(),
            $this->writableDirectories(),
        ];

        $failed = array_values(array_filter($checks, static fn (array $check): bool => ! $check['ok']));

        $this->option('json')
            ? $this->renderJson($checks, $failed)
            : $this->renderLines($checks, $failed);

        return $failed === [] ? self::SUCCESS : self::FAILURE;
    }

    /**
     * @return array{name: string, ok: bool, fix: string}
     */
    private function php(Filesystem $files): array
    {
        /** @var array{require: array{php: string}} $composer */
        $composer = json_decode($files->get(base_path('composer.json')), true, flags: JSON_THROW_ON_ERROR);

        $minimum = mb_ltrim($composer['require']['php'], '^~>=');

        return $this->check(
            sprintf('PHP %s', PHP_VERSION),
            version_compare(PHP_VERSION, $minimum, '>='),
            sprintf('Install PHP %s: herd install php@%1$s && herd use php@%1$s', $minimum),
        );
    }

    /**
     * @return array{name: string, ok: bool, fix: string}
     */
    private function extensions(): array
    {
        $missing = array_values(array_filter(
            self::REQUIRED_EXTENSIONS,
            static fn (string $extension): bool => ! extension_loaded($extension),
        ));

        return $this->check(
            'Required extensions',
            $missing === [],
            sprintf('Enable the missing extensions in php.ini: %s', implode(', ', $missing)),
        );
    }

    /**
     * @return array{name: string, ok: bool, fix: string}
     */
    private function coverageDriver(): array
    {
        return $this->check(
            'Coverage driver',
            extension_loaded('xdebug') || extension_loaded('pcov'),
            'composer test:unit needs one. Run it through Herd: herd coverage composer test:unit',
        );
    }

    /**
     * @return array{name: string, ok: bool, fix: string}
     */
    private function environmentFile(Filesystem $files): array
    {
        return $this->check(
            '.env file',
            $files->exists(base_path('.env')),
            'cp .env.example .env',
        );
    }

    /**
     * @return array{name: string, ok: bool, fix: string}
     */
    private function applicationKey(): array
    {
        return $this->check(
            'APP_KEY',
            is_string(config('app.key')) && config('app.key') !== '',
            'php artisan key:generate',
        );
    }

    /**
     * @return array{name: string, ok: bool, fix: string}
     */
    private function database(): array
    {
        $reachable = rescue(static function (): bool {
            DB::connection()->getPdo();

            return true;
        }, false, report: false);

        return $this->check(
            'Database connection',
            $reachable,
            'Check DB_* in .env. On the default sqlite driver: touch database/database.sqlite',
        );
    }

    /**
     * @return array{name: string, ok: bool, fix: string}
     */
    private function migrations(Migrator $migrator): array
    {
        $pending = rescue(static function () use ($migrator): int {
            $ran = $migrator->getRepository()->getRan();

            $files = array_keys($migrator->getMigrationFiles(database_path('migrations')));

            return count(array_diff($files, $ran));
        }, null, report: false);

        return $this->check(
            'Migrations',
            $pending === 0,
            'php artisan migrate',
        );
    }

    /**
     * @return array{name: string, ok: bool, fix: string}
     */
    private function bun(): array
    {
        return $this->check(
            'bun on PATH',
            Process::run('which bun')->successful(),
            'Install bun: curl -fsSL https://bun.sh/install | bash',
        );
    }

    /**
     * @return array{name: string, ok: bool, fix: string}
     */
    private function frontendDependencies(Filesystem $files): array
    {
        return $this->check(
            'node_modules',
            $files->isDirectory(base_path('node_modules')),
            'bun install',
        );
    }

    /**
     * @return array{name: string, ok: bool, fix: string}
     */
    private function viteManifest(Filesystem $files): array
    {
        return $this->check(
            'Vite manifest',
            $files->exists(public_path('build/manifest.json')),
            'bun run build, or bun run dev while you work',
        );
    }

    /**
     * Regenerates the types and asks git whether anything moved. A dirty file
     * here means the committed types no longer match the PHP they came from.
     *
     * @return array{name: string, ok: bool, fix: string}
     */
    private function generatedTypeScript(): array
    {
        Process::path(base_path())->run(PHP_BINARY.' artisan typescript:transform');

        $status = Process::path(base_path())->run('git status --porcelain -- '.self::GENERATED_TYPES);

        return $this->check(
            'Generated TypeScript',
            mb_trim($status->output()) === '',
            sprintf('%s was stale and has been rewritten. Run composer lint, then commit it.', self::GENERATED_TYPES),
        );
    }

    /**
     * @return array{name: string, ok: bool, fix: string}
     */
    private function writableDirectories(): array
    {
        return $this->check(
            'Writable directories',
            is_writable(storage_path()) && is_writable(base_path('bootstrap/cache')),
            'chmod -R ug+w storage bootstrap/cache',
        );
    }

    /**
     * @return array{name: string, ok: bool, fix: string}
     */
    private function check(string $name, bool $ok, string $fix): array
    {
        return ['name' => $name, 'ok' => $ok, 'fix' => $fix];
    }

    /**
     * @param  list<array{name: string, ok: bool, fix: string}>  $checks
     * @param  list<array{name: string, ok: bool, fix: string}>  $failed
     */
    private function renderJson(array $checks, array $failed): void
    {
        $this->output->writeln(json_encode([
            'ok' => $failed === [],
            'checks' => $checks,
        ], JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));
    }

    /**
     * The documentation worklist, if `wiki:audit` has ever run here. Not a
     * check: undocumented files do not stop this machine from building the
     * application, and turning that into a FAIL line is how a real signal gets
     * ignored.
     */
    private function wikiWorklist(): ?string
    {
        $path = base_path('wiki/_meta/audit.json');

        if (! is_file($path)) {
            return null;
        }

        $audit = json_decode((string) file_get_contents($path), true);

        $stale = is_array($audit) && is_array($audit['stale'] ?? null) ? count($audit['stale']) : 0;
        $undocumented = is_array($audit) && is_array($audit['undocumented'] ?? null) ? count($audit['undocumented']) : 0;

        if ($stale === 0 && $undocumented === 0) {
            return null;
        }

        return sprintf(
            'wiki: %d pages stale, %d files undocumented — run /document',
            $stale,
            $undocumented,
        );
    }

    /**
     * @param  list<array{name: string, ok: bool, fix: string}>  $checks
     * @param  list<array{name: string, ok: bool, fix: string}>  $failed
     */
    private function renderLines(array $checks, array $failed): void
    {
        $this->newLine();

        foreach ($checks as $check) {
            $this->components->twoColumnDetail(
                $check['name'],
                $check['ok'] ? '<fg=green>PASS</>' : '<fg=red>FAIL</>',
            );
        }

        foreach ($failed as $check) {
            $this->components->bulletList([sprintf('%s: %s', $check['name'], $check['fix'])]);
        }

        $worklist = $this->wikiWorklist();

        if ($worklist !== null) {
            $this->components->bulletList([$worklist]);
        }

        $failed === []
            ? $this->components->info('This machine is ready.')
            : $this->components->error(sprintf('%d check(s) failed.', count($failed)));
    }
}
