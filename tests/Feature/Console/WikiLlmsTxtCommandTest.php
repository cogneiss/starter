<?php

declare(strict_types=1);

use Illuminate\Support\Facades\File;

function llmsFixture(): array
{
    $root = sys_get_temp_dir().'/llms-'.bin2hex(random_bytes(4));

    File::ensureDirectoryExists($root.'/domains');
    File::put($root.'/index.md', "---\ntitle: Index\n---\n\n# Index\n\nStart here. Second sentence.\n");
    File::put($root.'/domains/http.md', "---\ntitle: HTTP\n---\n\n# HTTP\n\n| a | b |\n\nSee [[domains/other|the other page]] and [[x]]: more.\n");
    File::put($root.'/domains/bare.md', "# Bare\n\n- only a list\n");

    return [$root, $root.'/llms.txt'];
}

it('LlmsTxt writes sections, repo-relative links and first-sentence descriptions', function (): void {
    [$root, $out] = llmsFixture();

    $this->artisan('wiki:llms', ['--path' => $root, '--out' => $out])->assertExitCode(0);

    expect(file_get_contents($out))
        ->toContain("# Starter\n\n> ")
        ->toContain("## Domains\n\n- [domains/bare](wiki/domains/bare.md): domains/bare\n- [HTTP](wiki/domains/http.md): See the other page and x: more")
        ->toContain("## Overview\n\n- [Index](wiki/index.md): Start here");
});

it('LlmsTxt check passes on a fresh file and fails on drift or a missing file', function (): void {
    [$root, $out] = llmsFixture();

    $this->artisan('wiki:llms', ['--path' => $root, '--out' => $out, '--check' => true])->assertExitCode(1);

    $this->artisan('wiki:llms', ['--path' => $root, '--out' => $out])->assertExitCode(0);
    $this->artisan('wiki:llms', ['--path' => $root, '--out' => $out, '--check' => true])->assertExitCode(0);

    File::append($out, 'drift');
    $this->artisan('wiki:llms', ['--path' => $root, '--out' => $out, '--check' => true])->assertExitCode(1);
});

it('LlmsTxt fails when the directory holds no pages', function (): void {
    $root = sys_get_temp_dir().'/llms-empty-'.bin2hex(random_bytes(4));
    File::ensureDirectoryExists($root);

    $this->artisan('wiki:llms', ['--path' => $root])->assertExitCode(1);
});

it('LlmsTxt committed file matches the wiki', function (): void {
    $this->artisan('wiki:llms', ['--check' => true])->assertExitCode(0);
});
