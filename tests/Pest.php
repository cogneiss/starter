<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Sleep;
use Illuminate\Support\Str;
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

expect()->extend('toBeOne', fn () => $this->toBe(1));

/**
 * Serialises the handful of tests that read or write this repository's own
 * `wiki/_meta/audit.json`. It is one shared file, so two parallel workers
 * otherwise overwrite each other's fixture mid-assertion.
 */
function withWikiWorklistLock(Closure $work): void
{
    $handle = fopen(sys_get_temp_dir().'/starter-wiki-worklist.lock', 'c');

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
