<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
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
