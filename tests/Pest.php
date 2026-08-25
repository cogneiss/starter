<?php

declare(strict_types=1);

use App\Support\AiAvailability;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Sleep;
use Illuminate\Support\Str;
use Laravel\Ai\Ai;
use Laravel\Ai\Contracts\Agent;
use Symfony\Component\Finder\Finder;
use Tests\TestCase;

pest()->tia()->locally();

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->beforeEach(function (): void {
        Str::createRandomStringsNormally();
        Str::createUuidsNormally();
        Http::preventStrayRequests();
        Process::preventStrayProcesses();
        Sleep::fake();

        $this->freezeTime();
    })
    ->in('Browser', 'Feature', 'Unit');

/**
 * Nothing under `Feature/Ai` may reach a provider. Every agent the application
 * ships is faked here with no scripted answer, so a test that forgets its own
 * fake throws instead of dialling out. The protection is central rather than
 * per-file: a new test in the directory inherits it without asking.
 */
pest()->beforeEach(function (): void {
    foreach (classesIn(app_path('Ai/Agents'), 'App\Ai\Agents') as $agent) {
        if (is_subclass_of($agent, Agent::class)) {
            Ai::fakeAgent($agent)->preventStrayPrompts();
        }
    }
})->in('Feature/Ai');

/**
 * Evals grade prompts against real providers, so they are the one suite allowed
 * out to the network — and the one suite nothing blocking depends on. With no
 * key configured there is nothing to grade.
 */
pest()->extend(TestCase::class)
    ->group('Evals')
    ->beforeEach(function (): void {
        if (AiAvailability::faked()) {
            $this->markTestSkipped('No AI provider key is configured, so there is nothing to grade.');
        }
    })
    ->in('Evals');

expect()->extend('toBeOne', fn () => $this->toBe(1));

/**
 * A committed eval fixture: the cases to grade, and the model and date they
 * were written against.
 *
 * @return array{prompt: string, provider: string, model: string, captured_at: string, cases: list<array<string, mixed>>}
 */
function evalFixture(string $name): array
{
    /** @var array{prompt: string, provider: string, model: string, captured_at: string, cases: list<array<string, mixed>>} $fixture */
    $fixture = json_decode(
        (string) file_get_contents(__DIR__.'/Fixtures/Ai/'.$name.'.json'),
        true,
        512,
        JSON_THROW_ON_ERROR,
    );

    return $fixture;
}

/**
 * The fixture's cases, each wrapped so a dataset hands the whole case to the
 * test as one argument rather than spreading its keys across parameters.
 *
 * @return list<array{0: array<string, mixed>}>
 */
function evalCases(string $name): array
{
    return array_map(fn (array $case): array => [$case], evalFixture($name)['cases']);
}

/**
 * Serialises the handful of tests that read or write this repository's own
 * `wiki/_meta/audit.json`. It is one shared file, so two parallel workers
 * otherwise overwrite each other's fixture mid-assertion.
 */
function withWikiWorklistLock(Closure $work): void
{
    withRepositoryLock('wiki-worklist', $work);
}

/**
 * Serialises the tests that mutate this checkout's own source tree. The resource
 * generator writes real routes, a real permission and a real migration before
 * putting them back, and any test that reads the working tree meanwhile sees a
 * half-generated application rather than the committed one.
 */
function withCheckoutLock(Closure $work): void
{
    withRepositoryLock('checkout', $work);
}

/**
 * One named advisory lock, held for the duration of the given work.
 */
function withRepositoryLock(string $name, Closure $work): void
{
    $handle = fopen(sys_get_temp_dir().'/starter-'.$name.'.lock', 'c');

    flock($handle, LOCK_EX);

    try {
        $work();
    } finally {
        flock($handle, LOCK_UN);
        fclose($handle);
    }
}

/**
 * A scratch wiki for the documentation gates. One fixture per failure mode, so a
 * fixture can be as broken as it likes without breaking the real `wiki/`.
 */
function wikiFixture(string $name): string
{
    $root = storage_path('framework/testing/wiki-'.$name);

    File::deleteDirectory($root);
    File::ensureDirectoryExists($root);

    return $root;
}

function wikiPage(string $root, string $slug, string $frontmatter, string $body = ''): void
{
    $path = $root.'/'.$slug.'.md';

    File::ensureDirectoryExists(dirname($path));
    File::put($path, "---\n".$frontmatter."\n---\n\n".$body."\n");
}

/**
 * Every class under a PSR-4 directory, sorted. The convention guards walk these.
 *
 * @return list<class-string>
 */
function classesIn(string $directory, string $namespace): array
{
    $classes = [];

    foreach (Finder::create()->files()->in($directory)->name('*.php') as $file) {
        /** @var class-string $class */
        $class = $namespace.'\\'.Str::of($file->getRelativePathname())
            ->beforeLast('.php')
            ->replace(DIRECTORY_SEPARATOR, '\\')
            ->value();

        $classes[] = $class;
    }

    sort($classes);

    return $classes;
}
