<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Support\Install\Pruner;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;

use function Laravel\Prompts\confirm;
use function Laravel\Prompts\select;

/**
 * Prunes the layers a new project does not want, once, right after
 * `laravel new --using=cogneiss/starter`. It refuses to run without the marker
 * that create-project writes, so it cannot be pointed at the kit itself, and it
 * deletes its own files when it is done. Defaults keep everything.
 */
#[Description('Prune the layers this project does not use (run once, on a fresh install)')]
#[Signature('starter:install {--no-ai : Remove the AI layer} {--tenancy= : Organization resolver: session, subdomain or single} {--dry-run : List the steps without taking them} {--force : Run without the fresh-install marker} {--skip-composer : Leave composer.json packages alone}')]
final class StarterInstallCommand extends Command
{
    private const array RESOLVERS = ['session', 'subdomain', 'single'];

    public function handle(): int
    {
        $marker = base_path(config()->string('install.marker'));

        if (! is_file($marker) && ! $this->option('force')) {
            $this->components->error('Not a fresh install: the marker file is missing. Nothing was changed. Pass --force to run anyway.');

            return self::FAILURE;
        }

        $tenancy = $this->tenancy();

        if ($tenancy === null) {
            $this->components->error('--tenancy must be one of: '.implode(', ', self::RESOLVERS).'.');

            return self::FAILURE;
        }

        $removeAi = $this->option('no-ai') || ($this->input->isInteractive() && ! confirm('Keep the AI layer?', true));

        $pruner = new Pruner(base_path(), config()->array('install.layers'));
        $layers = array_filter(['ai' => $removeAi, 'self' => true]);

        foreach (array_keys($layers) as $layer) {
            foreach ($pruner->plan($layer) as $step) {
                $this->line(($this->option('dry-run') ? '[dry-run] ' : '').$step);
            }
        }

        if ($this->option('dry-run')) {
            return self::SUCCESS;
        }

        $pruner->setEnv('ORGANIZATIONS_RESOLVER', $tenancy);

        foreach (array_keys($layers) as $layer) {
            $pruner->apply($layer);
        }

        File::delete($marker);

        if ($removeAi && ! $this->option('skip-composer')) {
            Process::path(base_path())->forever()->run(['composer', 'remove', ...$pruner->composerPackages('ai'), '--no-interaction'])->throw();
        }

        $this->components->info('Done. Run: composer setup');

        return self::SUCCESS;
    }

    /**
     * The resolver from `--tenancy`, a prompt or the default; null when the
     * option names an unknown one.
     */
    private function tenancy(): ?string
    {
        $requested = $this->option('tenancy');

        if ($requested !== null) {
            return in_array($requested, self::RESOLVERS, true) ? $requested : null;
        }

        if (! $this->input->isInteractive()) {
            return 'session';
        }

        $picked = select('How do organizations resolve?', self::RESOLVERS, 'session', hint: 'Every account always has an organization; this picks how a request finds it.');

        return is_string($picked) ? $picked : 'session';
    }
}
