<?php

declare(strict_types=1);

/**
 * Real provider keys are exported into the shell on plenty of machines, and the
 * blocking suite must never see one: every AI test asserts the zero-key path,
 * and a key that leaked in would send a stray prompt to a real provider.
 *
 * phpunit.xml's `<env force>` only reaches getenv() and $_ENV, while Laravel
 * reads $_SERVER first, so the keys are cleared from all three here, before
 * the framework boots and reads them.
 *
 * `--evals` is the one opt-in to a real provider: the eval suite grades real
 * answers, and without this exception it could never see a key at all.
 */
if (! in_array('--evals', $_SERVER['argv'] ?? [], true)) {
    foreach (['ANTHROPIC_API_KEY', 'OPENAI_API_KEY', 'GEMINI_API_KEY'] as $key) {
        putenv($key.'=');
        $_ENV[$key] = '';
        $_SERVER[$key] = '';
    }
}

/**
 * A linked worktree (where .git is a file, not a directory) tests against its
 * own database, testing_<worktree>, which bin/worktree-setup creates, so two
 * worktrees running the suite at once never wipe each other's schema. Only the
 * phpunit.xml default is replaced; an explicit DB_DATABASE such as test:sqlite's
 * :memory: is left alone.
 */
if (is_file(__DIR__.'/../.git') && getenv('DB_DATABASE') === 'testing') {
    $database = 'testing_'.mb_strtolower((string) preg_replace('/[^A-Za-z0-9]+/', '_', basename(dirname(__DIR__))));

    putenv('DB_DATABASE='.$database);
    $_ENV['DB_DATABASE'] = $database;
    $_SERVER['DB_DATABASE'] = $database;
}

/**
 * pest-plugin-browser keeps the Playwright server's address in one state file
 * inside the package, and every pest process that is not a parallel worker
 * rewrites and then deletes it on exit, browser tests or not. A second run in
 * this checkout (a targeted run beside the gate, a pre-push hook, a test that
 * runs pest in a copy) would pull the file from under the gate's workers. So
 * each top-level run gets its own file, and its workers inherit the path.
 */
if (getenv('PARATEST') !== '1') {
    $stateFile = sys_get_temp_dir().'/pest-browser-'.getmypid().'.json';

    putenv('PEST_BROWSER_STATE_FILE='.$stateFile);
    $_ENV['PEST_BROWSER_STATE_FILE'] = $stateFile;
    $_SERVER['PEST_BROWSER_STATE_FILE'] = $stateFile;
}

require __DIR__.'/../vendor/autoload.php';
