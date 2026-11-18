<?php

declare(strict_types=1);

use Illuminate\Support\Facades\File;
use Symfony\Component\Process\Process as SymfonyProcess;

/**
 * Write a plan file under storage and return its path. The script is run as a
 * real process: it is plain PHP outside the app, so there is nothing to fake.
 */
function statusPlan(string $name, string $json): string
{
    $root = storage_path('framework/testing/recheck-status-'.$name);

    File::deleteDirectory($root);
    File::ensureDirectoryExists($root);
    file_put_contents($root.'/plan.status.json', $json);

    return $root.'/plan.status.json';
}

function recheck(string $file): SymfonyProcess
{
    $process = new SymfonyProcess([PHP_BINARY, base_path('bin/recheck-status'), $file]);
    $process->run();

    return $process;
}

it('records each phase from its exit code and fails when a passing phase stops holding', function (): void {
    $file = statusPlan('mixed', <<<'JSON'
    {
        "plan": "fixture",
        "follows": ["todo/other.status.json"],
        "phases": [
            { "id": 1, "name": "still holds", "status": "passing", "command": "true" },
            { "id": 2, "name": "broke", "status": "passing", "command": "echo boom && false" },
            { "id": 3, "name": "got fixed", "status": "failing", "command": "true" },
            { "id": 4, "name": "known gap", "status": "failing", "command": "false" }
        ]
    }

    JSON);

    $process = recheck($file);

    expect($process->getExitCode())->toBe(1)
        ->and($process->getErrorOutput())->toContain('plan #2 broke', 'boom')
        ->and($process->getOutput())->toContain("Now passing:\n  plan #3 got fixed")
        ->and((string) file_get_contents($file))->toBe(<<<'JSON'
        {
            "plan": "fixture",
            "follows": ["todo/other.status.json"],
            "phases": [
                { "id": 1, "name": "still holds", "status": "passing", "command": "true" },
                { "id": 2, "name": "broke", "status": "failing", "command": "echo boom && false" },
                { "id": 3, "name": "got fixed", "status": "passing", "command": "true" },
                { "id": 4, "name": "known gap", "status": "failing", "command": "false" }
            ]
        }

        JSON);
});

it('exits 0 and leaves the file alone when every recorded status still holds', function (): void {
    $json = <<<'JSON'
    {
      "phases": [
        { "id": 1, "name": "holds", "status": "passing", "command": "true" },
        { "id": 2, "name": "known gap", "status": "failing", "command": "false" }
      ]
    }

    JSON;
    $file = statusPlan('steady', $json);

    $process = recheck($file);

    expect($process->getExitCode())->toBe(0)
        ->and((string) file_get_contents($file))->toBe($json);
});

it('refuses a plan whose status is not passing or failing', function (): void {
    $file = statusPlan('partial', <<<'JSON'
    {
      "phases": [
        { "id": 1, "name": "half done", "status": "in_progress", "command": "true" }
      ]
    }

    JSON);

    $process = recheck($file);

    expect($process->getExitCode())->toBe(1)
        ->and($process->getErrorOutput())->toContain('needs exactly one "status"');
});
