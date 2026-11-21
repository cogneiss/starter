<?php

declare(strict_types=1);

use Illuminate\Support\Facades\File;
use Symfony\Component\Process\Process as SymfonyProcess;

/*
 * bin/tamper-guard runs from .githooks/commit-msg and refuses a staged change
 * that weakens a quality gate unless the message carries a Gate-Change
 * trailer. Every case builds a throwaway git repo and runs the guard, or a
 * real `git commit` through a copy of the real hook, as a process: the guard
 * is plain PHP outside the app, so there is nothing to fake.
 *
 * Case names start with their group label (`R1 refuses`, `commit-msg still`,
 * ...), which the contract checks select by.
 */

beforeEach(function (): void {
    $this->root = storage_path('framework/testing/tamper-guard-'.mb_substr(sha1($this->name().'|'.$this->dataName()), 0, 16));

    File::deleteDirectory($this->root);
    File::ensureDirectoryExists($this->root);
});

afterEach(function (): void {
    File::deleteDirectory($this->root);
});

/**
 * The environment of every fixture process. A git hook exports GIT_DIR and
 * GIT_INDEX_FILE, so none of the outer GIT_* variables pass through; user and
 * system config are off, and the ceiling stops git walking up into the kit.
 *
 * @return array<string, string|false>
 */
function tamperGuardEnv(): array
{
    $env = [];

    foreach (array_keys([...getenv(), ...$_SERVER, ...$_ENV]) as $key) {
        if (is_string($key) && str_starts_with($key, 'GIT_')) {
            $env[$key] = false;
        }
    }

    return [
        ...$env,
        'GIT_CONFIG_GLOBAL' => '/dev/null',
        'GIT_CONFIG_NOSYSTEM' => '1',
        'GIT_CEILING_DIRECTORIES' => storage_path('framework/testing'),
        'GIT_AUTHOR_NAME' => 'Fixture',
        'GIT_AUTHOR_EMAIL' => 'fixture@example.test',
        'GIT_COMMITTER_NAME' => 'Fixture',
        'GIT_COMMITTER_EMAIL' => 'fixture@example.test',
        'XDEBUG_MODE' => 'off',
    ];
}

function tamperGuardGit(string $cwd, string ...$args): string
{
    $process = new SymfonyProcess(['git', ...$args], $cwd, tamperGuardEnv());
    $process->mustRun();

    return $process->getOutput();
}

/**
 * @param  array<string, string|null>  $files  null deletes the file
 */
function tamperGuardWrite(string $repo, array $files): void
{
    foreach ($files as $path => $content) {
        if ($content === null) {
            File::delete($repo.'/'.$path);

            continue;
        }

        File::ensureDirectoryExists(dirname($repo.'/'.$path));
        file_put_contents($repo.'/'.$path, $content);
    }
}

/**
 * @param  array<string, string|null>  $files
 */
function tamperGuardStage(string $repo, array $files): void
{
    tamperGuardWrite($repo, $files);
    tamperGuardGit($repo, 'add', '-A');
}

/**
 * A repo at <root>/repo with commit hooks pointed at an empty directory. With
 * $base it holds one commit of those files; with null it has no HEAD yet.
 *
 * @param  array<string, string>|null  $base
 */
function tamperGuardRepo(string $root, ?array $base = []): string
{
    $repo = $root.'/repo';

    File::ensureDirectoryExists($root.'/hooks-off');
    File::ensureDirectoryExists($repo);
    tamperGuardGit($repo, 'init', '-q', '-b', 'main');
    tamperGuardGit($repo, 'config', 'core.hooksPath', $root.'/hooks-off');

    if ($base !== null) {
        tamperGuardStage($repo, $base);
        tamperGuardGit($repo, 'commit', '-q', '--allow-empty', '-m', 'chore: base');
    }

    return $repo;
}

/**
 * Run the guard in $cwd with the message written next to it.
 */
function tamperGuardRun(string $cwd, string $message = "chore: change\n"): SymfonyProcess
{
    $messageFile = dirname($cwd).'/message';
    file_put_contents($messageFile, $message);

    $process = new SymfonyProcess([PHP_BINARY, base_path('bin/tamper-guard'), $messageFile], $cwd, tamperGuardEnv());
    $process->run();

    return $process;
}

/**
 * @param  array<string, string>|null  $base
 * @param  array<string, string|null>  $staged
 */
function tamperGuardCheck(string $root, ?array $base, array $staged, string $message = "chore: change\n"): SymfonyProcess
{
    $repo = tamperGuardRepo($root, $base);
    tamperGuardStage($repo, $staged);

    return tamperGuardRun($repo, $message);
}

/**
 * @return list<string>
 */
function tamperGuardLines(SymfonyProcess $process): array
{
    return array_values(array_filter(explode("\n", $process->getErrorOutput()), fn (string $line): bool => $line !== ''));
}

/**
 * A repo mid-merge: main and branch x each add a commit on top of $base, and
 * x is merged into main without committing. With $worktree the merge happens
 * in a linked worktree. Returns the directory to run the guard in.
 *
 * @param  array<string, string>  $base
 * @param  array<string, string>  $onMain
 * @param  array<string, string>  $onBranch
 */
function tamperGuardMerge(string $root, array $base, array $onMain, array $onBranch, bool $worktree = false): string
{
    $repo = tamperGuardRepo($root, $base);

    tamperGuardGit($repo, 'checkout', '-q', '-b', 'x');
    tamperGuardStage($repo, $onBranch);
    tamperGuardGit($repo, 'commit', '-q', '-m', 'test: on x');
    tamperGuardGit($repo, 'checkout', '-q', 'main');
    tamperGuardStage($repo, $onMain);
    tamperGuardGit($repo, 'commit', '-q', '-m', 'test: on main');

    $cwd = $repo;

    if ($worktree) {
        $cwd = $root.'/worktree';
        tamperGuardGit($repo, 'worktree', 'add', '-q', '-b', 'w', $cwd, 'main');
    }

    tamperGuardGit($cwd, 'merge', '-q', '--no-commit', '--no-ff', 'x');

    return $cwd;
}

/**
 * A repo whose commit-msg hook is a copy of the real one, calling the real
 * guard through a bin/tamper-guard symlink as it does in the kit.
 *
 * @param  array<string, string>  $base
 */
function tamperGuardHookRepo(string $root, array $base): string
{
    $repo = tamperGuardRepo($root, $base);

    File::ensureDirectoryExists($root.'/hooks');
    File::copy(base_path('.githooks/commit-msg'), $root.'/hooks/commit-msg');
    chmod($root.'/hooks/commit-msg', 0755);
    File::ensureDirectoryExists($repo.'/bin');
    symlink(base_path('bin/tamper-guard'), $repo.'/bin/tamper-guard');
    file_put_contents($repo.'/.git/info/exclude', "bin/tamper-guard\n", FILE_APPEND);
    tamperGuardGit($repo, 'config', 'core.hooksPath', $root.'/hooks');

    return $repo;
}

function tamperGuardCommit(string $repo, string ...$args): SymfonyProcess
{
    $process = new SymfonyProcess(['git', 'commit', '-q', ...$args], $repo, tamperGuardEnv());
    $process->run();

    return $process;
}

function tamperGuardHead(string $repo): string
{
    return mb_trim(tamperGuardGit($repo, 'rev-parse', 'HEAD'));
}

function tamperGuardNeon(string $entries): string
{
    return "parameters:\n    level: max\n    ignoreErrors:\n".$entries."\n    paths:\n        - app\n";
}

function tamperGuardEntry(string $message, string $count = '1'): string
{
    return "        -\n            message: '{$message}'\n            path: app/A.php\n            count: {$count}\n";
}

/**
 * @param  list<array{0: string, 1: int}>  $entries  fingerprint and count
 */
function tamperGuardSloppy(array $entries): string
{
    return (string) json_encode([
        'schema' => 1,
        'entries' => array_map(fn (array $entry): array => ['rule' => 'SL101', 'fingerprint' => $entry[0], 'count' => $entry[1]], $entries),
    ], JSON_PRETTY_PRINT);
}

function tamperGuardTest(string $body): string
{
    return "<?php\n\n".$body."\n";
}

/**
 * @param  array<string, string|list<string>>  $scripts
 */
function tamperGuardComposer(array $scripts): string
{
    return (string) json_encode(['name' => 'fixture/app', 'scripts' => $scripts], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
}

function tamperGuardRoadmap(string $phaseOneCommand, string $phaseOneStatus = 'failing', string $otherCommand = 'true'): string
{
    return (string) json_encode(['phases' => [
        ['id' => '1', 'name' => 'guard', 'status' => $phaseOneStatus, 'command' => $phaseOneCommand],
        ['id' => '2', 'name' => 'other', 'status' => 'failing', 'command' => $otherCommand],
    ]], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
}

function tamperGuardCleanTest(): string
{
    return tamperGuardTest("test('a', function (): void {});");
}

function tamperGuardSkippedTest(): string
{
    return tamperGuardTest("test('a', function (): void {})->skip();");
}

/**
 * One refused change per rule, as base files and staged files.
 *
 * @return array<string, array{0: array<string, string>, 1: array<string, string|null>}>
 */
function tamperGuardRefusals(): array
{
    return [
        'an ignoreErrors entry' => [['phpstan.neon' => tamperGuardNeon(tamperGuardEntry('#^Foo$#'))], ['phpstan.neon' => tamperGuardNeon(tamperGuardEntry('#^Foo$#').tamperGuardEntry('#^Bar$#'))]],
        'an ignore comment' => [['app/A.php' => "<?php\n\n\$a = 1;\n"], ['app/A.php' => "<?php\n\n// @phpstan-ignore-next-line\n\$a = 1;\n"]],
        'a baseline entry' => [['.sloppy-baseline.json' => tamperGuardSloppy([['A::a', 1]])], ['.sloppy-baseline.json' => tamperGuardSloppy([['A::a', 1], ['B::b', 1]])]],
        'a skip marker' => [['tests/Feature/ATest.php' => tamperGuardCleanTest()], ['tests/Feature/ATest.php' => tamperGuardSkippedTest()]],
        'a lowered threshold' => [['composer.json' => tamperGuardComposer(['t' => 'pest --min=80'])], ['composer.json' => tamperGuardComposer(['t' => 'pest --min=70'])]],
        'a protected file' => [['bin/tamper-guard' => "x\n"], ['bin/tamper-guard' => "y\n"]],
    ];
}

test('R1 refuses', function (array $base, array $staged, string $path): void {
    $guard = tamperGuardCheck($this->root, $base, $staged);

    expect($guard->getExitCode())->toBe(1)
        ->and($guard->getErrorOutput())->toContain("R1 {$path}: ");
})->with([
    'R1 refuses a new entry in phpstan.neon' => [
        ['phpstan.neon' => tamperGuardNeon(tamperGuardEntry('#^Foo$#'))],
        ['phpstan.neon' => tamperGuardNeon(tamperGuardEntry('#^Foo$#').tamperGuardEntry('#^Bar$#'))],
        'phpstan.neon',
    ],
    'R1 refuses a widened message' => [
        ['phpstan.neon' => tamperGuardNeon(tamperGuardEntry('#^Foo$#'))],
        ['phpstan.neon' => tamperGuardNeon(tamperGuardEntry('#^Foo#'))],
        'phpstan.neon',
    ],
    'R1 refuses a raised count' => [
        ['phpstan.neon' => tamperGuardNeon(tamperGuardEntry('#^Foo$#'))],
        ['phpstan.neon' => tamperGuardNeon(tamperGuardEntry('#^Foo$#', '2'))],
        'phpstan.neon',
    ],
    'R1 refuses a new entry in the dead-code baseline' => [
        ['phpstan-deadcode-baseline.neon' => tamperGuardNeon(tamperGuardEntry('#^Foo$#'))],
        ['phpstan-deadcode-baseline.neon' => tamperGuardNeon(tamperGuardEntry('#^Foo$#').tamperGuardEntry('#^Bar$#'))],
        'phpstan-deadcode-baseline.neon',
    ],
    'R1 refuses an entry in a neon file new in the commit' => [
        ['README.md' => "readme\n"],
        ['phpstan-extra.neon' => tamperGuardNeon(tamperGuardEntry('#^Foo$#'))],
        'phpstan-extra.neon',
    ],
    'R1 refuses a changed inline list' => [
        ['phpstan.neon' => "parameters:\n    ignoreErrors: ['#^Foo$#']\n"],
        ['phpstan.neon' => "parameters:\n    ignoreErrors: ['#^Foo$#', '#^Bar$#']\n"],
        'phpstan.neon',
    ],
    'R1 refuses an entry under parameters.ignoreErrors in a .neon.dist' => [
        ['phpstan.neon.dist' => "parameters:\n    level: max\n"],
        ['phpstan.neon.dist' => tamperGuardNeon(tamperGuardEntry('#^Foo$#'))],
        'phpstan.neon.dist',
    ],
    'R1 refuses a new bare-string item' => [
        ['phpstan.neon' => tamperGuardNeon(tamperGuardEntry('#^Foo$#'))],
        ['phpstan.neon' => tamperGuardNeon(tamperGuardEntry('#^Foo$#')."        - '#^Baz$#'\n")],
        'phpstan.neon',
    ],
]);

test('R1 allows', function (array $base, array $staged): void {
    $guard = tamperGuardCheck($this->root, $base, $staged);

    expect($guard->getExitCode())->toBe(0)
        ->and($guard->getErrorOutput())->toBe('');
})->with([
    'removing an entry' => [
        ['phpstan.neon' => tamperGuardNeon(tamperGuardEntry('#^Foo$#').tamperGuardEntry('#^Bar$#'))],
        ['phpstan.neon' => tamperGuardNeon(tamperGuardEntry('#^Foo$#'))],
    ],
    'moving an entry between two staged neon files' => [
        ['phpstan.neon' => tamperGuardNeon(tamperGuardEntry('#^Foo$#').tamperGuardEntry('#^Bar$#')), 'phpstan-baseline.neon' => tamperGuardNeon('')],
        ['phpstan.neon' => tamperGuardNeon(tamperGuardEntry('#^Foo$#')), 'phpstan-baseline.neon' => tamperGuardNeon(tamperGuardEntry('#^Bar$#'))],
    ],
    'a comment and a blank line inside an existing entry' => [
        ['phpstan.neon' => tamperGuardNeon(tamperGuardEntry('#^Foo$#'))],
        ['phpstan.neon' => tamperGuardNeon("        -\n            # kept until the upstream fix\n            message: '#^Foo$#'\n\n            path: app/A.php\n            count: 1\n")],
    ],
]);

test('R2 refuses', function (array $base, array $staged, string $path): void {
    $guard = tamperGuardCheck($this->root, $base, $staged);

    expect($guard->getExitCode())->toBe(1)
        ->and($guard->getErrorOutput())->toContain("R2 {$path}: ");
})->with([
    'R2 refuses a next-line comment' => [['app/A.php' => "<?php\n\n\$a = 1;\n"], ['app/A.php' => "<?php\n\n// @phpstan-ignore-next-line\n\$a = 1;\n"], 'app/A.php'],
    'R2 refuses a line comment' => [['app/A.php' => "<?php\n\n\$a = 1;\n"], ['app/A.php' => "<?php\n\n\$a = 1; // @phpstan-ignore-line\n"], 'app/A.php'],
    'R2 refuses a block comment with an identifier' => [['app/A.php' => "<?php\n\n\$a = 1;\n"], ['app/A.php' => "<?php\n\n/* @phpstan-ignore argument.type */\n\$a = 1;\n"], 'app/A.php'],
    'R2 refuses a trailing comment with an identifier' => [['app/A.php' => "<?php\n\n\$a = 1;\n"], ['app/A.php' => "<?php\n\n\$a = 1; // @phpstan-ignore method.notFound\n"], 'app/A.php'],
    'R2 refuses a doc comment' => [['app/A.php' => "<?php\n\n\$a = 1;\n"], ['app/A.php' => "<?php\n\n/** @phpstan-ignore-next-line */\n\$a = 1;\n"], 'app/A.php'],
    'R2 refuses an ignore in a tests file' => [['tests/Unit/ATest.php' => "<?php\n\n\$a = 1;\n"], ['tests/Unit/ATest.php' => "<?php\n\n// @phpstan-ignore-next-line\n\$a = 1;\n"], 'tests/Unit/ATest.php'],
    'R2 refuses an ignore in a database file' => [['database/seeders/A.php' => "<?php\n\n\$a = 1;\n"], ['database/seeders/A.php' => "<?php\n\n// @phpstan-ignore-next-line\n\$a = 1;\n"], 'database/seeders/A.php'],
]);

test('R2 allows', function (array $base, array $staged): void {
    $guard = tamperGuardCheck($this->root, $base, $staged);

    expect($guard->getExitCode())->toBe(0)
        ->and($guard->getErrorOutput())->toBe('');
})->with([
    'the marker inside a string literal' => [['app/A.php' => "<?php\n\n\$a = 1;\n"], ['app/A.php' => "<?php\n\n\$a = '@phpstan-ignore';\n"]],
    'an ignore comment moved between two staged files' => [
        ['app/A.php' => "<?php\n\n// @phpstan-ignore-next-line\n\$a = 1;\n", 'app/B.php' => "<?php\n\n\$b = 1;\n"],
        ['app/A.php' => "<?php\n\n\$a = 1;\n", 'app/B.php' => "<?php\n\n// @phpstan-ignore-next-line\n\$b = 1;\n"],
    ],
    'the marker inside a heredoc' => [['app/A.php' => "<?php\n\n\$a = 1;\n"], ['app/A.php' => "<?php\n\n\$a = <<<TXT\n@phpstan-ignore\nTXT;\n"]],
]);

test('R3 refuses', function (array $base, array $staged): void {
    $guard = tamperGuardCheck($this->root, $base, $staged);

    expect($guard->getExitCode())->toBe(1)
        ->and($guard->getErrorOutput())->toContain('R3 .sloppy-baseline.json: ');
})->with([
    'R3 refuses a new entry' => [['.sloppy-baseline.json' => tamperGuardSloppy([['A::a', 1]])], ['.sloppy-baseline.json' => tamperGuardSloppy([['A::a', 1], ['B::b', 1]])]],
    'R3 refuses a raised count' => [['.sloppy-baseline.json' => tamperGuardSloppy([['A::a', 1]])], ['.sloppy-baseline.json' => tamperGuardSloppy([['A::a', 2]])]],
    'R3 refuses one fingerprint swapped for another' => [['.sloppy-baseline.json' => tamperGuardSloppy([['A::a', 1]])], ['.sloppy-baseline.json' => tamperGuardSloppy([['B::b', 1]])]],
    'R3 refuses a baseline new in the commit' => [['README.md' => "readme\n"], ['.sloppy-baseline.json' => tamperGuardSloppy([['A::a', 1]])]],
    'R3 refuses a blob that is not JSON' => [['.sloppy-baseline.json' => tamperGuardSloppy([['A::a', 1]])], ['.sloppy-baseline.json' => '{ "entries": [']],
    'R3 refuses a new entry reusing a fingerprint' => [['.sloppy-baseline.json' => tamperGuardSloppy([['A::a', 1]])], ['.sloppy-baseline.json' => tamperGuardSloppy([['A::a', 1], ['A::a', 1]])]],
    'R3 refuses JSON with no entries array' => [['.sloppy-baseline.json' => tamperGuardSloppy([['A::a', 1]])], ['.sloppy-baseline.json' => '{}']],
]);

test('R3 allows', function (array $base, array $staged): void {
    $guard = tamperGuardCheck($this->root, $base, $staged);

    expect($guard->getExitCode())->toBe(0)
        ->and($guard->getErrorOutput())->toBe('');
})->with([
    'a removed entry' => [['.sloppy-baseline.json' => tamperGuardSloppy([['A::a', 1], ['B::b', 1]])], ['.sloppy-baseline.json' => tamperGuardSloppy([['A::a', 1]])]],
    'a lowered count' => [['.sloppy-baseline.json' => tamperGuardSloppy([['A::a', 2]])], ['.sloppy-baseline.json' => tamperGuardSloppy([['A::a', 1]])]],
    'a deleted baseline' => [['.sloppy-baseline.json' => tamperGuardSloppy([['A::a', 1]]), 'README.md' => "readme\n"], ['.sloppy-baseline.json' => null]],
    'one of two same-fingerprint entries removed' => [['.sloppy-baseline.json' => tamperGuardSloppy([['A::a', 1], ['A::a', 1]])], ['.sloppy-baseline.json' => tamperGuardSloppy([['A::a', 1]])]],
]);

test('R4 refuses', function (?array $base, array $staged): void {
    $guard = tamperGuardCheck($this->root, $base, $staged);

    expect($guard->getExitCode())->toBe(1)
        ->and($guard->getErrorOutput())->toContain('R4 tests/Feature/ATest.php: ');
})->with([
    'R4 refuses it with skip' => [['tests/Feature/ATest.php' => tamperGuardCleanTest()], ['tests/Feature/ATest.php' => tamperGuardTest("it('a', function (): void {})->skip();")]],
    'R4 refuses it with skipOnCI' => [['tests/Feature/ATest.php' => tamperGuardCleanTest()], ['tests/Feature/ATest.php' => tamperGuardTest("it('a', function (): void {})->skipOnCI();")]],
    'R4 refuses it with todo' => [['tests/Feature/ATest.php' => tamperGuardCleanTest()], ['tests/Feature/ATest.php' => tamperGuardTest("it('a', function (): void {})->todo();")]],
    'R4 refuses it with flaky' => [['tests/Feature/ATest.php' => tamperGuardCleanTest()], ['tests/Feature/ATest.php' => tamperGuardTest("it('a', function (): void {})->flaky();")]],
    'R4 refuses it with only' => [['tests/Feature/ATest.php' => tamperGuardCleanTest()], ['tests/Feature/ATest.php' => tamperGuardTest("it('a', function (): void {})->only();")]],
    'R4 refuses test with skip' => [['tests/Feature/ATest.php' => tamperGuardCleanTest()], ['tests/Feature/ATest.php' => tamperGuardSkippedTest()]],
    'R4 refuses describe with skip' => [['tests/Feature/ATest.php' => tamperGuardCleanTest()], ['tests/Feature/ATest.php' => tamperGuardTest("describe('a', function (): void {})->skip();")]],
    'R4 refuses arch with skip' => [['tests/Feature/ATest.php' => tamperGuardCleanTest()], ['tests/Feature/ATest.php' => tamperGuardTest("arch('a')->skip();")]],
    'R4 refuses it with a dataset then skip' => [['tests/Feature/ATest.php' => tamperGuardCleanTest()], ['tests/Feature/ATest.php' => tamperGuardTest("it('a', function (int \$n): void {})->with([1])->skip();")]],
    'R4 refuses this markTestSkipped' => [['tests/Feature/ATest.php' => tamperGuardCleanTest()], ['tests/Feature/ATest.php' => tamperGuardTest("test('a', function (): void { \$this->markTestSkipped('later'); });")]],
    'R4 refuses this markTestIncomplete' => [['tests/Feature/ATest.php' => tamperGuardCleanTest()], ['tests/Feature/ATest.php' => tamperGuardTest("test('a', function (): void { \$this->markTestIncomplete('later'); });")]],
    'R4 refuses self markTestSkipped' => [['tests/Feature/ATest.php' => tamperGuardCleanTest()], ['tests/Feature/ATest.php' => tamperGuardTest("final class ATest extends TestCase\n{\n    public function testA(): void\n    {\n        self::markTestSkipped('later');\n    }\n}")]],
    'R4 refuses a closure-less it' => [['tests/Feature/ATest.php' => tamperGuardCleanTest()], ['tests/Feature/ATest.php' => tamperGuardTest("it('a');")]],
    'R4 refuses a staged file that does not parse' => [['tests/Feature/ATest.php' => tamperGuardCleanTest()], ['tests/Feature/ATest.php' => tamperGuardTest("test('a', function (): void {")]],
    'R4 refuses a skip in a first commit' => [null, ['tests/Feature/ATest.php' => tamperGuardSkippedTest()]],
    'R4 refuses a global todo' => [['tests/Feature/ATest.php' => tamperGuardCleanTest()], ['tests/Feature/ATest.php' => tamperGuardTest("todo('a');")]],
    'R4 refuses a base that does not parse and an index with a skip' => [['tests/Feature/ATest.php' => tamperGuardTest("test('a', function (): void {")], ['tests/Feature/ATest.php' => tamperGuardSkippedTest()]],
]);

test('R4 refuses a skip added while resolving a merge that is in neither parent', function (): void {
    $cwd = tamperGuardMerge($this->root, ['tests/Feature/ATest.php' => tamperGuardCleanTest()], ['README.md' => "main\n"], ['tests/Feature/BTest.php' => tamperGuardSkippedTest()]);
    tamperGuardStage($cwd, ['tests/Feature/CTest.php' => tamperGuardSkippedTest()]);

    $guard = tamperGuardRun($cwd);

    expect($guard->getExitCode())->toBe(1)
        ->and($guard->getErrorOutput())->toContain('R4 tests/Feature/');
});

test('R4 allows', function (array $base, array $staged): void {
    $guard = tamperGuardCheck($this->root, $base, $staged);

    expect($guard->getExitCode())->toBe(0)
        ->and($guard->getErrorOutput())->toBe('');
})->with([
    'a collection skip' => [['tests/Feature/ATest.php' => tamperGuardCleanTest()], ['tests/Feature/ATest.php' => tamperGuardTest("test('a', function (): void { collect([1])->skip(1); });")]],
    'a request only' => [['tests/Feature/ATest.php' => tamperGuardCleanTest()], ['tests/Feature/ATest.php' => tamperGuardTest("test('a', function (): void { \$request->only('a'); });")]],
    'a collection only' => [['tests/Feature/ATest.php' => tamperGuardCleanTest()], ['tests/Feature/ATest.php' => tamperGuardTest("test('a', function (): void { collect()->only(['a']); });")]],
    'a skip moved between two staged test files' => [
        ['tests/Feature/ATest.php' => tamperGuardSkippedTest(), 'tests/Feature/BTest.php' => tamperGuardCleanTest()],
        ['tests/Feature/ATest.php' => tamperGuardCleanTest(), 'tests/Feature/BTest.php' => tamperGuardSkippedTest()],
    ],
    'an unrelated line in a file with a conditional skip' => [
        ['tests/Feature/ATest.php' => tamperGuardTest("test('a', function (): void {})->skip(fn (): bool => true);")],
        ['tests/Feature/ATest.php' => tamperGuardTest("test('a', function (): void { expect(1)->toBe(1); })->skip(fn (): bool => true);")],
    ],
    'a base that does not parse and a clean index' => [['tests/Feature/ATest.php' => tamperGuardTest("test('a', function (): void {")], ['tests/Feature/ATest.php' => tamperGuardCleanTest()]],
    'a zero-argument test call' => [['tests/Feature/ATest.php' => tamperGuardCleanTest()], ['tests/Feature/ATest.php' => tamperGuardTest("test('a', function (): void { test()->actingAs(\$user); });")]],
]);

test('R4 allows a merge whose skip comes from one parent', function (bool $worktree): void {
    $cwd = tamperGuardMerge($this->root, ['tests/Feature/ATest.php' => tamperGuardCleanTest()], ['README.md' => "main\n"], ['tests/Feature/BTest.php' => tamperGuardSkippedTest()], $worktree);

    $guard = tamperGuardRun($cwd);

    expect($guard->getExitCode())->toBe(0)
        ->and($guard->getErrorOutput())->toBe('');
})->with([
    'in the main worktree' => [false],
    'in a linked worktree' => [true],
]);

test('R4 allows a test file holding a skip renamed with git mv', function (): void {
    $repo = tamperGuardRepo($this->root, ['tests/Feature/ATest.php' => tamperGuardSkippedTest()]);
    tamperGuardGit($repo, 'mv', 'tests/Feature/ATest.php', 'tests/Feature/BTest.php');

    $guard = tamperGuardRun($repo);

    expect($guard->getExitCode())->toBe(0)
        ->and($guard->getErrorOutput())->toBe('');
});

test('R5 refuses', function (array $base, string $staged, string $key): void {
    $guard = tamperGuardCheck($this->root, ['composer.json' => tamperGuardComposer($base)], ['composer.json' => $staged]);

    expect($guard->getExitCode())->toBe(1)
        ->and($guard->getErrorOutput())->toContain("R5 composer.json: {$key}");
})->with([
    'R5 refuses a lowered min' => [['t' => 'pest --min=80'], tamperGuardComposer(['t' => 'pest --min=70']), 'scripts.t --min #1 lowered'],
    'R5 refuses a lowered exactly' => [['t' => 'pest --exactly=100.0'], tamperGuardComposer(['t' => 'pest --exactly=99.0']), 'scripts.t --exactly #1 lowered'],
    'R5 refuses a removed exactly' => [['t' => 'pest --exactly=100.0'], tamperGuardComposer(['t' => 'pest']), 'scripts.t --exactly #1 removed'],
    'R5 refuses a min moved to another script' => [['t' => 'pest --min=80', 'u' => 'pest'], tamperGuardComposer(['t' => 'pest', 'u' => 'pest --min=80']), 'scripts.t --min #1 removed'],
    'R5 refuses a deleted script that held a flag' => [['t' => 'pest --min=80', 'u' => 'pint'], tamperGuardComposer(['u' => 'pint']), 'scripts.t --min #1 removed'],
    'R5 refuses a lowered min in the space form' => [['t' => 'pest --min 80'], tamperGuardComposer(['t' => 'pest --min 70']), 'scripts.t --min #1 lowered'],
    'R5 refuses a composer.json that is not JSON' => [['t' => 'pest --min=80'], '{ "scripts": ', 'not valid JSON'],
    'R5 refuses a lowered flag in an array-valued script' => [['t' => ['pint', 'pest --min=80']], tamperGuardComposer(['t' => ['pint', 'pest --min=70']]), 'scripts.t --min #1 lowered'],
    'R5 refuses the second of two min flags lowered' => [['t' => 'pest --min=80 && infection --min=60'], tamperGuardComposer(['t' => 'pest --min=80 && infection --min=50']), 'scripts.t --min #2 lowered'],
]);

test('R5 allows', function (array $base, array $staged): void {
    $guard = tamperGuardCheck($this->root, ['composer.json' => tamperGuardComposer($base)], ['composer.json' => tamperGuardComposer($staged)]);

    expect($guard->getExitCode())->toBe(0)
        ->and($guard->getErrorOutput())->toBe('');
})->with([
    'a raised min' => [['t' => 'pest --min=80'], ['t' => 'pest --min=90']],
    'a new flag' => [['t' => 'pest --min=80'], ['t' => 'pest --min=80 --exactly=100.0']],
    'an unrelated script edited' => [['t' => 'pest --min=80', 'u' => 'pint'], ['t' => 'pest --min=80', 'u' => 'pint --test']],
    'one of two min flags raised' => [['t' => 'pest --min=80 && infection --min=60'], ['t' => 'pest --min=90 && infection --min=60']],
]);

test('R6 refuses', function (array $staged, string $path): void {
    $guard = tamperGuardCheck($this->root, [
        'bin/tamper-guard' => "guard\n",
        '.githooks/commit-msg' => "hook\n",
        'bin/prove-control.sh' => "prove\n",
        'tests/Feature/Docs/TamperGuardTest.php' => "<?php\n",
        'tests/Mutations/tamper-guard-r1-off.patch' => "patch\n",
        'todo/roadmap.status.json' => tamperGuardRoadmap('vendor/bin/pest'),
    ], $staged);

    expect($guard->getExitCode())->toBe(1)
        ->and($guard->getErrorOutput())->toContain("R6 {$path}: ");
})->with([
    'R6 refuses the guard modified' => [['bin/tamper-guard' => "weaker\n"], 'bin/tamper-guard'],
    'R6 refuses the guard deleted' => [['bin/tamper-guard' => null], 'bin/tamper-guard'],
    'R6 refuses the hook modified' => [['.githooks/commit-msg' => "weaker\n"], '.githooks/commit-msg'],
    'R6 refuses prove-control modified' => [['bin/prove-control.sh' => "weaker\n"], 'bin/prove-control.sh'],
    'R6 refuses the guard test deleted' => [['tests/Feature/Docs/TamperGuardTest.php' => null], 'tests/Feature/Docs/TamperGuardTest.php'],
    'R6 refuses a guard patch modified' => [['tests/Mutations/tamper-guard-r1-off.patch' => "weaker\n"], 'tests/Mutations/tamper-guard-r1-off.patch'],
    'R6 refuses the phase 1 command changed' => [['todo/roadmap.status.json' => tamperGuardRoadmap('true')], 'todo/roadmap.status.json'],
]);

test('R6 refuses a mode change of the guard', function (): void {
    $repo = tamperGuardRepo($this->root, ['bin/tamper-guard' => "guard\n"]);
    tamperGuardGit($repo, 'update-index', '--chmod=+x', 'bin/tamper-guard');

    $guard = tamperGuardRun($repo);

    expect($guard->getExitCode())->toBe(1)
        ->and($guard->getErrorOutput())->toContain('R6 bin/tamper-guard: ');
});

test('R6 refuses the guard test renamed with git mv', function (): void {
    $repo = tamperGuardRepo($this->root, ['tests/Feature/Docs/TamperGuardTest.php' => "<?php\n"]);
    tamperGuardGit($repo, 'mv', 'tests/Feature/Docs/TamperGuardTest.php', 'tests/Feature/Docs/OtherTest.php');

    $guard = tamperGuardRun($repo);

    expect($guard->getExitCode())->toBe(1)
        ->and($guard->getErrorOutput())->toContain('R6 tests/Feature/Docs/TamperGuardTest.php: ');
});

test('R6 allows another phase command and the phase 1 status to change', function (): void {
    $guard = tamperGuardCheck(
        $this->root,
        ['todo/roadmap.status.json' => tamperGuardRoadmap('vendor/bin/pest')],
        ['todo/roadmap.status.json' => tamperGuardRoadmap('vendor/bin/pest', 'passing', 'false')],
    );

    expect($guard->getExitCode())->toBe(0)
        ->and($guard->getErrorOutput())->toBe('');
});

test('Gate-Change lets', function (array $base, array $staged): void {
    $guard = tamperGuardCheck($this->root, $base, $staged, "chore: change\n\nGate-Change: reason\n");

    expect($guard->getExitCode())->toBe(0)
        ->and($guard->getOutput())->toBe('')
        ->and($guard->getErrorOutput())->toBe('');
})->with(tamperGuardRefusals());

test('Gate-Change lets the trailer through', function (string $message): void {
    $guard = tamperGuardCheck($this->root, ['tests/Feature/ATest.php' => tamperGuardCleanTest()], ['tests/Feature/ATest.php' => tamperGuardSkippedTest()], $message);

    expect($guard->getExitCode())->toBe(0)
        ->and($guard->getOutput())->toBe('')
        ->and($guard->getErrorOutput())->toBe('');
})->with([
    'in lower case' => ["chore: change\n\ngate-change: reason\n"],
    'as line 1' => ["Gate-Change: reason\n"],
]);

test('Gate-Change lets a run outside any git repo through', function (): void {
    File::ensureDirectoryExists($this->root.'/outside');

    $guard = tamperGuardRun($this->root.'/outside', "chore: change\n\nGate-Change: reason\n");

    expect($guard->getExitCode())->toBe(0)
        ->and($guard->getOutput())->toBe('')
        ->and($guard->getErrorOutput())->toBe('');
});

test('Gate-Change does not count', function (string $message): void {
    $guard = tamperGuardCheck($this->root, ['tests/Feature/ATest.php' => tamperGuardCleanTest()], ['tests/Feature/ATest.php' => tamperGuardSkippedTest()], $message);

    expect($guard->getExitCode())->toBe(1)
        ->and($guard->getErrorOutput())->toContain('R4 tests/Feature/ATest.php: ');
})->with([
    'Gate-Change does not count when bare' => ["chore: change\n\nGate-Change:\n"],
    'Gate-Change does not count when only spaces follow' => ["chore: change\n\nGate-Change:   \n"],
    'Gate-Change does not count in a comment line' => ["chore: change\n\n# Gate-Change: reason\n"],
    'Gate-Change does not count when not at column 0' => ["chore: change\n\nfoo Gate-Change: reason\n"],
]);

test('guard allows', function (?array $base, array $staged): void {
    $guard = tamperGuardCheck($this->root, $base, $staged);

    expect($guard->getExitCode())->toBe(0)
        ->and($guard->getOutput())->toBe('')
        ->and($guard->getErrorOutput())->toBe('');
})->with([
    'a README line' => [['README.md' => "readme\n"], ['README.md' => "readme\nmore\n"]],
    'an empty staged set' => [['README.md' => "readme\n"], []],
    'a first commit with no findings' => [null, ['README.md' => "readme\n", 'tests/Feature/ATest.php' => tamperGuardCleanTest()]],
]);

test('guard reads the index, not an unstaged skip marker', function (): void {
    $repo = tamperGuardRepo($this->root, ['tests/Feature/ATest.php' => tamperGuardCleanTest(), 'README.md' => "readme\n"]);
    tamperGuardStage($repo, ['README.md' => "readme\nmore\n"]);
    tamperGuardWrite($repo, ['tests/Feature/ATest.php' => tamperGuardSkippedTest()]);

    expect(tamperGuardRun($repo)->getExitCode())->toBe(0);
});

test('guard reads the index, not an unstaged edit that removes a staged skip marker', function (): void {
    $repo = tamperGuardRepo($this->root, ['tests/Feature/ATest.php' => tamperGuardCleanTest()]);
    tamperGuardStage($repo, ['tests/Feature/ATest.php' => tamperGuardSkippedTest()]);
    tamperGuardWrite($repo, ['tests/Feature/ATest.php' => tamperGuardCleanTest()]);

    $guard = tamperGuardRun($repo);

    expect($guard->getExitCode())->toBe(1)
        ->and($guard->getErrorOutput())->toContain('R4 tests/Feature/ATest.php: ');
});

test('guard prints one line per finding', function (array $base, array $staged, array $expected): void {
    $guard = tamperGuardCheck($this->root, $base, $staged);
    $lines = tamperGuardLines($guard);

    expect($guard->getExitCode())->toBe(1)
        ->and($guard->getOutput())->toBe('')
        ->and($lines)->toHaveCount(count($expected))
        ->and($lines)->each->toMatch('/^R[1-6] \S+: /');

    foreach ($expected as $i => $prefix) {
        expect($lines[$i])->toStartWith($prefix);
    }
})->with([
    'an R2 and an R4 finding' => [
        ['app/A.php' => "<?php\n\n\$a = 1;\n", 'tests/Feature/ATest.php' => tamperGuardCleanTest()],
        ['app/A.php' => "<?php\n\n// @phpstan-ignore-next-line\n\$a = 1;\n", 'tests/Feature/ATest.php' => tamperGuardSkippedTest()],
        ['R2 app/A.php: ', 'R4 tests/Feature/ATest.php: '],
    ],
    'two new entries in one neon file' => [
        ['phpstan.neon' => tamperGuardNeon(tamperGuardEntry('#^Foo$#'))],
        ['phpstan.neon' => tamperGuardNeon(tamperGuardEntry('#^Foo$#').tamperGuardEntry('#^Bar$#').tamperGuardEntry('#^Baz$#'))],
        ['R1 phpstan.neon: '],
    ],
    'two protected files changed' => [
        ['bin/tamper-guard' => "guard\n", '.githooks/commit-msg' => "hook\n"],
        ['bin/tamper-guard' => "weaker\n", '.githooks/commit-msg' => "weaker\n"],
        ['R6 .githooks/commit-msg: ', 'R6 bin/tamper-guard: '],
    ],
    'two lowered flags' => [
        ['composer.json' => tamperGuardComposer(['t' => 'pest --min=80', 'u' => 'pest --exactly=100.0'])],
        ['composer.json' => tamperGuardComposer(['t' => 'pest --min=70', 'u' => 'pest --exactly=99.0'])],
        ['R5 composer.json: ', 'R5 composer.json: '],
    ],
    'ignores added in two files and removed in a third' => [
        ['0.php' => "<?php\n\n// @phpstan-ignore-next-line\n\$a = 1;\n", 'a.php' => "<?php\n\n\$a = 1;\n", 'b.php' => "<?php\n\n\$b = 1;\n"],
        ['0.php' => "<?php\n\n\$a = 1;\n", 'a.php' => "<?php\n\n// @phpstan-ignore-next-line\n\$a = 1;\n", 'b.php' => "<?php\n\n// @phpstan-ignore-next-line\n\$b = 1;\n"],
        ['R2 a.php: '],
    ],
]);

test('guard exits 2 on misuse', function (array $arguments): void {
    $process = new SymfonyProcess([PHP_BINARY, base_path('bin/tamper-guard'), ...$arguments], $this->root, tamperGuardEnv());
    $process->run();

    expect($process->getExitCode())->toBe(2)
        ->and($process->getErrorOutput())->toContain('usage: bin/tamper-guard <message-file>');
})->with([
    'with no argument' => [[]],
    'with a message file that does not exist' => [['/nonexistent/message']],
]);

test('guard exits 2 outside any git repo', function (): void {
    File::ensureDirectoryExists($this->root.'/outside');

    $guard = tamperGuardRun($this->root.'/outside');

    expect($guard->getExitCode())->toBe(2)
        ->and($guard->getErrorOutput())->not->toBe('')
        ->and($guard->getErrorOutput())->not->toMatch('/^R[1-6] /m');
});

test('commit-msg refuses a commit that stages a skip marker', function (): void {
    $repo = tamperGuardHookRepo($this->root, ['tests/Feature/ATest.php' => tamperGuardCleanTest()]);
    $head = tamperGuardHead($repo);
    tamperGuardStage($repo, ['tests/Feature/ATest.php' => tamperGuardSkippedTest()]);

    $commit = tamperGuardCommit($repo, '-m', 'test: skip a case');

    expect($commit->getExitCode())->not->toBe(0)
        ->and(tamperGuardHead($repo))->toBe($head)
        ->and($commit->getErrorOutput())->toContain('R4 tests/Feature/ATest.php: ', 'Gate-Change:');
});

test('commit-msg refuses the same marker through git commit -a', function (): void {
    $repo = tamperGuardHookRepo($this->root, ['tests/Feature/ATest.php' => tamperGuardCleanTest()]);
    $head = tamperGuardHead($repo);
    tamperGuardWrite($repo, ['tests/Feature/ATest.php' => tamperGuardSkippedTest()]);

    $commit = tamperGuardCommit($repo, '-a', '-m', 'test: skip a case');

    expect($commit->getExitCode())->not->toBe(0)
        ->and(tamperGuardHead($repo))->toBe($head)
        ->and($commit->getErrorOutput())->toContain('R4 tests/Feature/ATest.php: ');
});

test('commit-msg refuses a non-conventional subject with a finding and still prints the finding', function (): void {
    $repo = tamperGuardHookRepo($this->root, ['tests/Feature/ATest.php' => tamperGuardCleanTest()]);
    tamperGuardStage($repo, ['tests/Feature/ATest.php' => tamperGuardSkippedTest()]);

    $commit = tamperGuardCommit($repo, '-m', 'skip a case');

    expect($commit->getExitCode())->not->toBe(0)
        ->and($commit->getErrorOutput())->toContain('R4 tests/Feature/ATest.php: ');
});

test('commit-msg refuses when the guard is a stub that exits 3', function (): void {
    $repo = tamperGuardHookRepo($this->root, ['README.md' => "readme\n"]);
    $head = tamperGuardHead($repo);
    File::delete($repo.'/bin/tamper-guard');
    file_put_contents($repo.'/bin/tamper-guard', "#!/bin/sh\nexit 3\n");
    chmod($repo.'/bin/tamper-guard', 0755);
    tamperGuardStage($repo, ['README.md' => "readme\nmore\n"]);

    $commit = tamperGuardCommit($repo, '-m', 'docs: more readme');

    expect($commit->getExitCode())->not->toBe(0)
        ->and(tamperGuardHead($repo))->toBe($head)
        ->and($commit->getErrorOutput())->toContain('Gate-Change:');
});

test('commit-msg accepts the skip marker with a Gate-Change trailer', function (): void {
    $repo = tamperGuardHookRepo($this->root, ['tests/Feature/ATest.php' => tamperGuardCleanTest()]);
    $head = tamperGuardHead($repo);
    tamperGuardStage($repo, ['tests/Feature/ATest.php' => tamperGuardSkippedTest()]);

    $commit = tamperGuardCommit($repo, '-m', 'test: skip a case', '-m', 'Gate-Change: reason');

    expect($commit->getExitCode())->toBe(0, $commit->getErrorOutput())
        ->and(tamperGuardHead($repo))->not->toBe($head);
});

test('commit-msg still refuses a non-conventional subject with no finding', function (): void {
    $repo = tamperGuardHookRepo($this->root, ['README.md' => "readme\n"]);
    $head = tamperGuardHead($repo);
    tamperGuardStage($repo, ['README.md' => "readme\nmore\n"]);

    $commit = tamperGuardCommit($repo, '-m', 'more readme');

    expect($commit->getExitCode())->not->toBe(0)
        ->and(tamperGuardHead($repo))->toBe($head)
        ->and($commit->getErrorOutput())->toContain('conventional commit');
});

test('commit-msg still refuses a feat that stages app code without a todo status', function (): void {
    $repo = tamperGuardHookRepo($this->root, ['README.md' => "readme\n"]);
    $head = tamperGuardHead($repo);
    tamperGuardStage($repo, ['app/X.php' => "<?php\n"]);

    $commit = tamperGuardCommit($repo, '-m', 'feat: add x');

    expect($commit->getExitCode())->not->toBe(0)
        ->and(tamperGuardHead($repo))->toBe($head)
        ->and($commit->getErrorOutput())->toContain('Todo: none');
});

test('commit-msg still accepts that feat with Todo: none', function (): void {
    $repo = tamperGuardHookRepo($this->root, ['README.md' => "readme\n"]);
    $head = tamperGuardHead($repo);
    tamperGuardStage($repo, ['app/X.php' => "<?php\n"]);

    $commit = tamperGuardCommit($repo, '-m', 'feat: add x', '-m', 'Todo: none');

    expect($commit->getExitCode())->toBe(0, $commit->getErrorOutput())
        ->and(tamperGuardHead($repo))->not->toBe($head);
});

test('commit-msg still accepts a Merge branch subject', function (): void {
    $repo = tamperGuardHookRepo($this->root, ['README.md' => "readme\n"]);
    $head = tamperGuardHead($repo);
    tamperGuardStage($repo, ['README.md' => "readme\nmore\n"]);

    $commit = tamperGuardCommit($repo, '-m', "Merge branch 'x'");

    expect($commit->getExitCode())->toBe(0, $commit->getErrorOutput())
        ->and(tamperGuardHead($repo))->not->toBe($head);
});

test('commit-msg refuses a path commit whose unstaged file adds a skip marker', function (): void {
    $repo = tamperGuardHookRepo($this->root, ['tests/a.php' => tamperGuardCleanTest(), 'tests/b.php' => tamperGuardCleanTest()]);
    $head = tamperGuardHead($repo);
    tamperGuardWrite($repo, ['tests/b.php' => tamperGuardTest("test('b', function (): void { expect(1)->toBe(1); });")]);
    tamperGuardGit($repo, 'add', 'tests/b.php');
    tamperGuardWrite($repo, ['tests/a.php' => tamperGuardSkippedTest()]);

    $commit = tamperGuardCommit($repo, '-m', 'test: change a', '--', 'tests/a.php');

    expect($commit->getExitCode())->not->toBe(0)
        ->and(tamperGuardHead($repo))->toBe($head)
        ->and($commit->getErrorOutput())->toContain('R4 tests/a.php: ');
});

test('commit-msg accepts a path commit while a skip marker stays staged in another file', function (): void {
    $repo = tamperGuardHookRepo($this->root, ['tests/a.php' => tamperGuardCleanTest(), 'tests/b.php' => tamperGuardCleanTest()]);
    $head = tamperGuardHead($repo);
    tamperGuardWrite($repo, ['tests/b.php' => tamperGuardSkippedTest()]);
    tamperGuardGit($repo, 'add', 'tests/b.php');
    tamperGuardWrite($repo, ['tests/a.php' => tamperGuardTest("test('a', function (): void { expect(1)->toBe(1); });")]);

    $commit = tamperGuardCommit($repo, '-m', 'test: change a', '--', 'tests/a.php');

    expect($commit->getExitCode())->toBe(0, $commit->getErrorOutput())
        ->and(tamperGuardHead($repo))->not->toBe($head);
});

test('commit-msg refuses a skip marker under a git-written subject', function (string $subject): void {
    $repo = tamperGuardHookRepo($this->root, ['tests/Feature/ATest.php' => tamperGuardCleanTest()]);
    $head = tamperGuardHead($repo);
    tamperGuardStage($repo, ['tests/Feature/ATest.php' => tamperGuardSkippedTest()]);

    $commit = tamperGuardCommit($repo, '-m', $subject);

    expect($commit->getExitCode())->not->toBe(0)
        ->and(tamperGuardHead($repo))->toBe($head)
        ->and($commit->getErrorOutput())->toContain('R4 tests/Feature/ATest.php: ');
})->with([
    'commit-msg refuses a merge subject' => ["Merge branch 'x'"],
    'commit-msg refuses a fixup subject' => ['fixup! x'],
]);
