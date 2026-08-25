<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Support\AiAvailability;
use App\Support\AiRetrieval;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Database\Migrations\Migrator;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Process;
use Laravel\Ai\Enums\Lab;

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
            $this->postgresDriver(),
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
            ? $this->renderJson($checks, $failed, $this->optional())
            : $this->renderLines($checks, $failed, $this->optional());

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
     * PostgreSQL is the primary database because pgvector lives there. SQLite is
     * still supported for everything except vector search, so this only fails on
     * a checkout that is actually pointed at pgsql.
     *
     * @return array{name: string, ok: bool, fix: string}
     */
    private function postgresDriver(): array
    {
        return $this->check(
            'pdo_pgsql extension',
            extension_loaded('pdo_pgsql') || $this->driver() !== 'pgsql',
            'Enable pdo_pgsql in php.ini, or switch to the sqlite fallback documented in .env.example',
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
            sprintf('Database connection (%s)', $this->driver()),
            $reachable,
            'Check DB_* in .env. On the sqlite fallback: touch database/database.sqlite',
        );
    }

    /**
     * The configured driver, which is readable without opening a connection.
     */
    private function driver(): string
    {
        return rescue(static fn (): string => DB::connection()->getDriverName(), 'unknown', report: false);
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
     * Third-party credentials. Missing is not a failure: the kit is designed to
     * run with none of them, so a blank GOOGLE_CLIENT_ID is a feature that is
     * off, not a broken machine. Reporting it as FAIL next to a missing APP_KEY
     * is how people learn to ignore this command.
     *
     * @return list<array{name: string, set: bool, disables: string}>
     */
    private function optional(): array
    {
        return [
            $this->credential('Social login', 'the provider buttons stay hidden', [
                'services.google.client_id',
                'services.github.client_id',
                'services.microsoft.client_id',
            ]),
            // Judged by the transport rather than by a key, because each one
            // takes different credentials and two of them take none at all.
            [
                'name' => 'Mail transport',
                'set' => ! in_array(config('mail.default'), ['log', 'array'], true),
                'disables' => 'mail is written to the log, not sent',
            ],
            // Reported here rather than as a check: a machine without it builds,
            // tests and serves everything but vector search.
            [
                'name' => 'pgvector extension',
                'set' => $this->hasVectorExtension(),
                'disables' => 'vector search is unavailable',
            ],
            $this->retrieval(),
            $this->aiProviders(),
            $this->aiGateway(),
            $this->aiQuotas(),
            $this->aiPricing(),
            $this->credential('S3 disk', 'the s3 disk cannot be reached', ['filesystems.disks.s3.key']),
            $this->credential('Slack notifications', 'Slack notifications are dropped', [
                'services.slack.notifications.bot_user_oauth_token',
            ]),
        ];
    }

    /**
     * Whether retrieval (RAG) can run here, and which half is missing when it
     * cannot. Not a check: an application without retrieval answers without it.
     *
     * @return array{name: string, set: bool, disables: string}
     */
    private function retrieval(): array
    {
        $reason = AiRetrieval::unavailableReason();

        return [
            'name' => 'AI retrieval (RAG)',
            'set' => $reason === null,
            'disables' => sprintf('agents answer without retrieval — %s', $reason ?? 'nothing'),
        ];
    }

    /**
     * Which AI providers hold a key. Names only — printing a key, or even the
     * first few characters of one, is how they end up in a pasted terminal log.
     *
     * @return array{name: string, set: bool, disables: string}
     */
    private function aiProviders(): array
    {
        $providers = AiAvailability::providers();

        return [
            'name' => $providers === [] ? 'AI providers' : sprintf('AI providers: %s', implode(', ', $providers)),
            'set' => $providers !== [],
            'disables' => 'no provider is configured',
        ];
    }

    /**
     * @return array{name: string, set: bool, disables: string}
     */
    private function aiGateway(): array
    {
        return [
            'name' => sprintf('AI live gateway (default tier: %s)', AiAvailability::defaultTier()),
            'set' => ! AiAvailability::faked(),
            'disables' => 'every agent answers from the fake gateway',
        ];
    }

    /**
     * The three AI quotas, reported as one line. A zero anywhere means that
     * limit is off, which is a decision someone should have made on purpose.
     *
     * @return array{name: string, set: bool, disables: string}
     */
    private function aiQuotas(): array
    {
        $user = config()->integer('ai.quotas.user_requests_per_hour');
        $organization = config()->integer('ai.quotas.org_requests_per_day');
        $budget = config()->integer('ai.quotas.org_budget_micros_per_month');

        return [
            'name' => sprintf(
                'AI quotas (%d/user/hour, %d/org/day, $%s/org/month)',
                $user,
                $organization,
                number_format($budget / 1_000_000, 2),
            ),
            'set' => $user > 0 && $organization > 0 && $budget > 0,
            'disables' => 'a quota set to zero refuses every request',
        ];
    }

    /**
     * Whether every model this application can actually reach has a price. A
     * configured model with no entry in `ai.pricing` bills as zero forever, and
     * silent zero-cost accounting is worse than an unpriced model: the budget
     * quota never trips, so nothing ever tells anyone.
     *
     * @return array{name: string, set: bool, disables: string}
     */
    private function aiPricing(): array
    {
        /** @var array<string, array{provider?: mixed, model?: mixed}> $tiers */
        $tiers = config('ai.tiers', []);

        $unpriced = [];

        foreach ($tiers as $tier => $configuration) {
            $provider = $configuration['provider'] ?? null;
            $model = $configuration['model'] ?? null;

            if (! $provider instanceof Lab || ! is_string($model) || $model === '') {
                continue;
            }

            if (! is_array(config(sprintf('ai.pricing.%s.%s', $provider->value, $model)))) {
                $unpriced[] = sprintf('%s (%s)', $model, $tier);
            }
        }

        return [
            'name' => $unpriced === []
                ? 'AI pricing'
                : sprintf('AI pricing: no price for %s', implode(', ', $unpriced)),
            'set' => $unpriced === [],
            'disables' => 'those runs are recorded as costing nothing, so the budget quota never trips',
        ];
    }

    /**
     * Whether `vector` is installed on the connected database. `ai:install`
     * creates it; a sqlite checkout never has it.
     */
    private function hasVectorExtension(): bool
    {
        return rescue(
            fn (): bool => $this->driver() === 'pgsql'
                && DB::selectOne("select 1 from pg_extension where extname = 'vector'") !== null,
            false,
            report: false,
        );
    }

    /**
     * @param  list<string>  $keys
     * @return array{name: string, set: bool, disables: string}
     */
    private function credential(string $name, string $disables, array $keys): array
    {
        $set = array_any($keys, static function (string $key): bool {
            $value = config($key);

            return is_string($value) && $value !== '';
        });

        return ['name' => $name, 'set' => $set, 'disables' => $disables];
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
     * @param  list<array{name: string, set: bool, disables: string}>  $optional
     */
    private function renderJson(array $checks, array $failed, array $optional): void
    {
        $this->output->writeln(json_encode([
            'ok' => $failed === [],
            'checks' => $checks,
            'optional' => $optional,
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
     * @param  list<array{name: string, set: bool, disables: string}>  $optional
     */
    private function renderLines(array $checks, array $failed, array $optional): void
    {
        $this->newLine();

        foreach ($checks as $check) {
            $this->components->twoColumnDetail(
                $check['name'],
                $check['ok'] ? '<fg=green>PASS</>' : '<fg=red>FAIL</>',
            );
        }

        foreach ($optional as $credential) {
            $this->components->twoColumnDetail(
                $credential['name'],
                $credential['set'] ? '<fg=green>SET</>' : sprintf('<fg=gray>off — %s</>', $credential['disables']),
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
