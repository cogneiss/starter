<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Str;

#[Description('Scaffold an onboarding step')]
#[Signature('app:make-onboarding-step
    {name : The studly name of the step, e.g. ConnectBilling}
    {--force : Overwrite the file if it already exists}
    {--base= : Generate into this directory instead of the application root}')]
final class MakeOnboardingStepCommand extends Command
{
    public function handle(Filesystem $files): int
    {
        $name = Str::studly((string) $this->argument('name'));

        if (in_array(preg_match('/^[A-Za-z]+$/', $name), [0, false], true)) {
            $this->components->error('The step name has to be alphabetic, e.g. ConnectBilling.');

            return self::FAILURE;
        }

        $base = mb_rtrim($this->option('base') ?? $this->laravel->basePath(), '/');
        $path = 'app/Onboarding/Steps/'.$name.'Step.php';

        if ($files->exists($base.'/'.$path) && $this->option('force') === false) {
            $this->components->error($path.' already exists. Re-run with --force to overwrite it.');

            return self::FAILURE;
        }

        $files->ensureDirectoryExists(dirname($base.'/'.$path));
        $files->put($base.'/'.$path, strtr($files->get($this->laravel->basePath('stubs/onboarding/step.stub')), [
            '{{ class }}' => $name,
            '{{ kebab }}' => Str::kebab($name),
            '{{ title }}' => Str::headline($name),
        ]));

        $this->components->bulletList([$path]);
        $this->components->info('The registry picks it up on the next request. Fill in isComplete() and it is live.');

        return self::SUCCESS;
    }
}
