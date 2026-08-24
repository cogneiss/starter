<?php

declare(strict_types=1);

use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;

/**
 * `/document` parses `wiki/_meta/audit.json`, so its shape is a contract and gets
 * asserted key by key rather than by count.
 *
 * @return array{generated_from: string|null, stale: list<array{page: string, reason: string, code_refs: list<string>}>, undocumented: list<array{path: string, reason: string}>, orphaned: list<array{page: string, reason: string}>}
 */
function auditOf(string $root): array
{
    test()->artisan('wiki:audit', ['--path' => $root])->assertExitCode(0);

    return json_decode((string) file_get_contents($root.'/_meta/audit.json'), true, flags: JSON_THROW_ON_ERROR);
}

/**
 * The two git questions the command asks, answered separately: HEAD, and the
 * last commit touching a ref. Array-form commands render as `['git' 'log' …]`,
 * so the patterns have to match the quoted form.
 */
beforeEach(function (): void {
    Process::fake([
        "*'rev-parse'*" => Process::result(output: "deadbee\n"),
        "*'log'*" => Process::result(output: "abc1234 2026-09-30\n"),
    ]);
});

it('writes the worklist with every key /document reads', function (): void {
    $root = wikiFixture('audit-shape');

    wikiPage($root, 'domains/documentation', <<<'YAML'
        title: Documentation
        status: current
        supersedes: []
        code_refs:
            - app/Support/WikiPage.php
        updated: 2026-08-24
        YAML);

    $audit = auditOf($root);

    expect(array_keys($audit))->toBe(['generated_from', 'stale', 'undocumented', 'orphaned'])
        ->and($audit['generated_from'])->toBe('deadbee');
});

it('reports a page whose code was committed after it was written', function (): void {
    $root = wikiFixture('audit-stale');

    wikiPage($root, 'domains/documentation', <<<'YAML'
        title: Documentation
        status: current
        supersedes: []
        code_refs:
            - app/Support/WikiPage.php
        updated: 2026-08-24
        YAML);

    expect(auditOf($root)['stale'])->toBe([[
        'page' => 'domains/documentation',
        'reason' => 'app/Support/WikiPage.php changed in abc1234',
        'code_refs' => ['app/Support/WikiPage.php'],
    ]]);
});

it('leaves a page alone when its code has not moved since', function (): void {
    $root = wikiFixture('audit-current');

    wikiPage($root, 'domains/documentation', <<<'YAML'
        title: Documentation
        status: current
        supersedes: []
        code_refs:
            - app/Support/WikiPage.php
        updated: 2099-01-01
        YAML);

    expect(auditOf($root)['stale'])->toBe([]);
});

it('cannot judge staleness for a page with no updated date', function (): void {
    $root = wikiFixture('audit-undated');

    wikiPage($root, 'domains/documentation', <<<'YAML'
        title: Documentation
        status: current
        supersedes: []
        code_refs:
            - app/Support/WikiPage.php
        updated: []
        YAML);

    expect(auditOf($root)['stale'])->toBe([]);
});

it('lists application files no page claims, and only those', function (): void {
    $root = wikiFixture('audit-undocumented');

    wikiPage($root, 'domains/documentation', <<<'YAML'
        title: Documentation
        status: current
        supersedes: []
        code_refs:
            - app/Support/WikiPage.php
        updated: 2099-01-01
        YAML);

    $paths = array_column(auditOf($root)['undocumented'], 'path');

    expect($paths)->not->toContain('app/Support/WikiPage.php')
        ->and($paths)->toContain('app/Models/User.php')
        ->and(auditOf($root)['undocumented'][0]['reason'])->toBe('no wiki page lists this file in code_refs');
});

it('reports a page whose code is all gone as orphaned', function (): void {
    $root = wikiFixture('audit-orphaned');

    wikiPage($root, 'domains/legacy-thing', <<<'YAML'
        title: Legacy thing
        status: current
        supersedes: []
        code_refs:
            - app/Actions/ThisWasDeleted.php
        updated: 2099-01-01
        YAML);

    expect(auditOf($root)['orphaned'])->toBe([[
        'page' => 'domains/legacy-thing',
        'reason' => 'all code_refs deleted',
    ]]);
});

it('does not call a page with no code_refs orphaned', function (): void {
    $root = wikiFixture('audit-refless');

    wikiPage($root, 'index', <<<'YAML'
        title: Index
        status: current
        supersedes: []
        code_refs: []
        updated: 2099-01-01
        YAML);

    expect(auditOf($root)['orphaned'])->toBe([]);
});

it('does not call a page with one surviving ref orphaned', function (): void {
    $root = wikiFixture('audit-partial');

    wikiPage($root, 'domains/half-gone', <<<'YAML'
        title: Half gone
        status: current
        supersedes: []
        code_refs:
            - app/Actions/ThisWasDeleted.php
            - app/Support/WikiPage.php
        updated: 2099-01-01
        YAML);

    expect(auditOf($root)['orphaned'])->toBe([]);
});

it('defaults to the wiki this repository ships', function (): void {
    // This one writes to the real worklist, so it puts back whatever was there.
    // Leaving the faked git output behind makes `app:doctor` report hundreds of
    // stale pages that do not exist, and a report nobody believes is ignored.
    withWikiWorklistLock(function (): void {
        $path = base_path('wiki/_meta/audit.json');
        $before = is_file($path) ? (string) file_get_contents($path) : null;

        try {
            $this->artisan('wiki:audit')->assertExitCode(0);

            $audit = json_decode((string) file_get_contents($path), true, flags: JSON_THROW_ON_ERROR);

            expect($audit['generated_from'])->toBe('deadbee');
        } finally {
            if ($before === null) {
                File::delete($path);
            } else {
                File::put($path, $before);
            }
        }
    });
});
