<?php

declare(strict_types=1);

use Illuminate\Support\Facades\File;
use Inertia\Testing\AssertableInertia;

it('Changelog renders CHANGELOG.md as html', function (): void {
    $this->get(route('changelog'))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
            ->component('changelog')
            ->where('html', fn (string $html): bool => str_contains($html, '<h2>') && str_contains($html, '<h3>')));
});

it('Changelog escapes raw html', function (): void {
    $path = storage_path('framework/testing/changelog.md');
    File::ensureDirectoryExists(dirname($path));
    File::put($path, "## 1.0\n\n<script>alert(1)</script>");
    config()->set('site.changelog_path', $path);

    $this->get(route('changelog'))
        ->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
            ->where('html', fn (string $html): bool => str_contains($html, '&lt;script&gt;') && ! str_contains($html, '<script>')));

    File::deleteDirectories(storage_path('framework/testing'));
});

it('Changelog is empty when the file is missing', function (): void {
    config()->set('site.changelog_path', storage_path('framework/testing/missing.md'));

    $this->get(route('changelog'))
        ->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page->where('html', ''));
});
