<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;

/**
 * One fixture wiki per failure mode. Each writes pages into a scratch directory
 * and lints that instead of `wiki/`, so a fixture can be as broken as it likes.
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
 * No commit for anything, so the stale rule stands down and the other rules can
 * be tested on their own.
 */
beforeEach(function (): void {
    Process::fake(['*' => Process::result(output: '')]);
});

it('passes over the wiki this repository ships', function (): void {
    $this->artisan('wiki:lint')
        ->assertExitCode(0)
        ->expectsOutputToContain('no rot.');
});

it('fails when the directory holds no pages', function (): void {
    $root = wikiFixture('empty');

    $this->artisan('wiki:lint', ['--path' => $root])
        ->assertExitCode(1)
        ->expectsOutputToContain('No wiki pages found');
});

it('fails when frontmatter keys are missing', function (): void {
    $root = wikiFixture('frontmatter');

    wikiPage($root, 'thin', "title: Thin\nstatus: current");

    $this->artisan('wiki:lint', ['--path' => $root])
        ->assertExitCode(1)
        ->expectsOutputToContain('missing frontmatter: supersedes, code_refs, updated');
});

it('fails when a page has no frontmatter at all', function (): void {
    $root = wikiFixture('bodyonly');

    File::put($root.'/loose.md', "# Loose\n\nNo frontmatter here.\n");

    $this->artisan('wiki:lint', ['--path' => $root])
        ->assertExitCode(1)
        ->expectsOutputToContain('loose is missing frontmatter');
});

it('fails when a code_ref no longer exists', function (): void {
    $root = wikiFixture('deadref');

    wikiPage($root, 'gone', <<<'YAML'
        title: Gone
        status: current
        supersedes: []
        code_refs:
            - app/Actions/ThisWasDeleted.php
        updated: 2026-08-24
        YAML);

    $this->artisan('wiki:lint', ['--path' => $root])
        ->assertExitCode(1)
        ->expectsOutputToContain('app/Actions/ThisWasDeleted.php, which does not exist');
});

it('rejects a directory in code_refs', function (): void {
    $root = wikiFixture('dirref');

    wikiPage($root, 'broad', <<<'YAML'
        title: Broad
        status: current
        supersedes: []
        code_refs:
            - app/Actions
        updated: 2026-08-24
        YAML);

    $this->artisan('wiki:lint', ['--path' => $root])
        ->assertExitCode(1)
        ->expectsOutputToContain('lists the directory app/Actions');
});

it('fails on a link to a page that does not exist', function (): void {
    $root = wikiFixture('dangling');

    wikiPage($root, 'index', <<<'YAML'
        title: Index
        status: current
        supersedes: []
        code_refs: []
        updated: 2026-08-24
        YAML, 'See [[domains/imaginary]] and [[index|this page]].');

    $this->artisan('wiki:lint', ['--path' => $root])
        ->assertExitCode(1)
        ->expectsOutputToContain('links to [[domains/imaginary]], which is not a page');
});

it('fails when a link to a superseded page has no replacement anywhere', function (): void {
    $root = wikiFixture('orphaned-supersede');

    wikiPage($root, 'index', <<<'YAML'
        title: Index
        status: current
        supersedes: []
        code_refs: []
        updated: 2026-08-24
        YAML, 'See [[old]].');

    wikiPage($root, 'old', <<<'YAML'
        title: Old
        status: superseded
        supersedes: []
        code_refs: []
        updated: 2026-08-24
        YAML);

    $this->artisan('wiki:lint', ['--path' => $root])
        ->assertExitCode(1)
        ->expectsOutputToContain('links to the superseded page [[old]], and no page supersedes it');
});

it('fails when a superseded link does not also point at its replacement', function (): void {
    $root = wikiFixture('unrouted-supersede');

    wikiPage($root, 'index', <<<'YAML'
        title: Index
        status: current
        supersedes: []
        code_refs: []
        updated: 2026-08-24
        YAML, 'See [[old]].');

    wikiPage($root, 'old', <<<'YAML'
        title: Old
        status: superseded
        supersedes: []
        code_refs: []
        updated: 2026-08-24
        YAML);

    wikiPage($root, 'new', <<<'YAML'
        title: New
        status: current
        supersedes:
            - old
        code_refs: []
        updated: 2026-08-24
        YAML);

    $this->artisan('wiki:lint', ['--path' => $root])
        ->assertExitCode(1)
        ->expectsOutputToContain('without linking its replacement. Link [[new]]');
});

it('passes when a superseded link is accompanied by its replacement', function (): void {
    $root = wikiFixture('routed-supersede');

    wikiPage($root, 'index', <<<'YAML'
        title: Index
        status: current
        supersedes: []
        code_refs: []
        updated: 2026-08-24
        YAML, 'See [[old]], now [[new]].');

    wikiPage($root, 'old', <<<'YAML'
        title: Old
        status: superseded
        supersedes: []
        code_refs: []
        updated: 2026-08-24
        YAML);

    wikiPage($root, 'new', <<<'YAML'
        title: New
        status: current
        supersedes:
            - old
        code_refs: []
        updated: 2026-08-24
        YAML);

    $this->artisan('wiki:lint', ['--path' => $root])
        ->assertExitCode(0)
        ->expectsOutputToContain('3 wiki pages, no rot.');
});

it('fails when the code has been committed since the page was written', function (): void {
    Process::fake(['*' => Process::result(output: "abc1234 2026-09-30\n")]);

    $root = wikiFixture('stale');

    wikiPage($root, 'architecture/six-method-spine', <<<'YAML'
        title: Six method spine
        status: current
        supersedes: []
        code_refs:
            - app/Resources/ResourceContract.php
        updated: 2026-08-24
        YAML);

    expect(Artisan::call('wiki:lint', ['--path' => $root]))->toBe(1);

    // The failure has to name the page, the file, and the commit that outran it:
    // "stale" on its own is not actionable.
    expect(Artisan::output())
        ->toContain('architecture/six-method-spine is stale')
        ->toContain('app/Resources/ResourceContract.php changed in abc1234 on 2026-09-30')
        ->toContain('Bumping updated: on its own clears this gate and hides the drift');
});

it('treats a commit on the day the page was updated as current', function (): void {
    Process::fake(['*' => Process::result(output: "abc1234 2026-08-24\n")]);

    $root = wikiFixture('same-day');

    wikiPage($root, 'spine', <<<'YAML'
        title: Spine
        status: current
        supersedes: []
        code_refs:
            - app/Resources/ResourceContract.php
        updated: 2026-08-24
        YAML);

    $this->artisan('wiki:lint', ['--path' => $root])
        ->assertExitCode(0);
});

it('skips the stale rule when git cannot answer', function (): void {
    Process::fake(['*' => Process::result(output: 'not-a-log-line', exitCode: 128)]);

    $root = wikiFixture('no-git');

    wikiPage($root, 'spine', <<<'YAML'
        title: Spine
        status: current
        supersedes: []
        code_refs:
            - app/Resources/ResourceContract.php
        updated: 2026-08-24
        YAML);

    $this->artisan('wiki:lint', ['--path' => $root])
        ->assertExitCode(0);
});

it('skips the stale rule when the page carries no updated date', function (): void {
    Process::fake(['*' => Process::result(output: "abc1234 2026-09-30\n")]);

    $root = wikiFixture('undated');

    wikiPage($root, 'spine', <<<'YAML'
        title: Spine
        status: current
        supersedes: []
        code_refs:
            - app/Resources/ResourceContract.php
        updated: []
        YAML);

    $this->artisan('wiki:lint', ['--path' => $root])
        ->assertExitCode(0);
});

it('ignores _meta pages and unparseable frontmatter is a frontmatter failure', function (): void {
    $root = wikiFixture('meta');

    wikiPage($root, '_meta/lint', "this: is: not: yaml\n");

    wikiPage($root, 'good', <<<'YAML'
        title: Good
        status: current
        supersedes: []
        code_refs: []
        updated: 2026-08-24
        YAML);

    $this->artisan('wiki:lint', ['--path' => $root])
        ->assertExitCode(0)
        ->expectsOutputToContain('1 wiki pages, no rot.');
});

it('reports an unparseable page as missing every key', function (): void {
    $root = wikiFixture('badyaml');

    wikiPage($root, 'broken', "\ttitle: tabbed\n  bad indent\n");

    $this->artisan('wiki:lint', ['--path' => $root])
        ->assertExitCode(1)
        ->expectsOutputToContain('broken is missing frontmatter: title, status, supersedes, code_refs, updated');
});

it('ignores a non-string entry in a list', function (): void {
    $root = wikiFixture('mixed-list');

    wikiPage($root, 'mixed', <<<'YAML'
        title: Mixed
        status: current
        supersedes: []
        code_refs:
            - composer.json
            - nested:
                  key: value
        updated: 2026-08-24
        YAML);

    $this->artisan('wiki:lint', ['--path' => $root])
        ->assertExitCode(0);
});

it('passes over an empty frontmatter block as a missing-key failure', function (): void {
    $root = wikiFixture('emptyfm');

    File::put($root.'/blank.md', "---\n---\n\nBody.\n");

    $this->artisan('wiki:lint', ['--path' => $root])
        ->assertExitCode(1)
        ->expectsOutputToContain('blank is missing frontmatter');
});

it('ignores a code_refs value that is not a list', function (): void {
    $root = wikiFixture('scalar-refs');

    wikiPage($root, 'scalar', <<<'YAML'
        title: Scalar
        status: current
        supersedes: []
        code_refs: app/Actions/ThisWasDeleted.php
        updated: 2026-08-24
        YAML);

    $this->artisan('wiki:lint', ['--path' => $root])
        ->assertExitCode(0);
});

it('treats a frontmatter block that is a list as no frontmatter at all', function (): void {
    $root = wikiFixture('list-frontmatter');

    wikiPage($root, 'listed', "- title\n- status");

    $this->artisan('wiki:lint', ['--path' => $root])
        ->assertExitCode(1)
        ->expectsOutputToContain('listed is missing frontmatter');
});
