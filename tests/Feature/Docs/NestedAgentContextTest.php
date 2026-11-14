<?php

declare(strict_types=1);

use App\Console\Commands\AgentsContextCommand;
use Illuminate\Support\Facades\File;

/**
 * Nested AGENTS.md files are output of `agents:context`, and `.ai/rules/index.md`
 * is the only source. The first test is the drift gate over the real tree; the
 * rest run the command against a throwaway root so each branch is pinned.
 */
function agentContextRoot(): string
{
    $root = sys_get_temp_dir().'/agents-context-'.bin2hex(random_bytes(4));

    File::ensureDirectoryExists($root.'/.ai/rules');
    File::ensureDirectoryExists($root.'/app/Ai');
    File::ensureDirectoryExists($root.'/app/Plain');
    File::ensureDirectoryExists($root.'/.github');
    File::ensureDirectoryExists($root.'/docs');
    File::put($root.'/.ai/rules/index.md', implode("\n", [
        '| Applies to | Rule file |',
        '| --- | --- |',
        '| app/Ai/** | .ai/rules/ai.md |',
        '| app/** | .ai/rules/app.md |',
        '| app/Plain/** | .ai/rules/boost/plain.md |',
        '| .github/**, gone/** | .ai/rules/ci.md |',
        '| docs/** | .ai/rules/docs.md |',
        '| **, {AGENTS,CLAUDE}.md | .ai/rules/general.md |',
        '',
    ]));

    return $root;
}

it('keeps the nested AGENTS.md files in sync with .ai/rules/index.md', function (): void {
    $this->artisan('agents:context', ['--check' => true])->assertExitCode(0);
});

it('writes one file per indexed directory, skipping app, dot directories and missing ones', function (): void {
    $root = agentContextRoot();

    $this->artisan('agents:context', ['--root' => $root])->assertExitCode(0);

    expect(File::exists($root.'/app/AGENTS.md'))->toBeFalse()
        ->and(File::exists($root.'/.github/AGENTS.md'))->toBeFalse()
        ->and(File::exists($root.'/gone/AGENTS.md'))->toBeFalse()
        ->and(File::get($root.'/app/Ai/AGENTS.md'))
        ->toStartWith(AgentsContextCommand::MARKER)
        ->toContain("- `.ai/rules/ai.md`\n- `.ai/rules/app.md`")
        ->toContain('- `ai-layer` (`.ai/skills/ai-layer/SKILL.md`)')
        ->not->toContain('general.md')
        ->and(File::get($root.'/app/Plain/AGENTS.md'))
        ->toContain('.ai/rules/boost/plain.md')
        ->and(File::get($root.'/docs/AGENTS.md'))
        ->toContain('.ai/rules/docs.md')
        ->not->toContain('Load the matching skill');
});

it('fails the check on a missing, edited or leftover file and passes once rewritten', function (): void {
    $root = agentContextRoot();

    $this->artisan('agents:context', ['--root' => $root, '--check' => true])->assertExitCode(1);

    $this->artisan('agents:context', ['--root' => $root])->assertExitCode(0);
    $this->artisan('agents:context', ['--root' => $root, '--check' => true])->assertExitCode(0);

    File::append($root.'/app/Ai/AGENTS.md', 'hand edit');
    File::ensureDirectoryExists($root.'/app/Left');
    File::put($root.'/app/Left/AGENTS.md', AgentsContextCommand::MARKER."\nold\n");
    File::put($root.'/app/Plain/Hand.md', 'unrelated');
    $this->artisan('agents:context', ['--root' => $root, '--check' => true])->assertExitCode(1);

    $this->artisan('agents:context', ['--root' => $root])->assertExitCode(0);

    expect(File::exists($root.'/app/Left/AGENTS.md'))->toBeFalse()
        ->and(File::get($root.'/app/Ai/AGENTS.md'))->not->toContain('hand edit');
});

it('leaves a hand-written AGENTS.md alone', function (): void {
    $root = agentContextRoot();
    File::ensureDirectoryExists($root.'/app/Mine');
    File::put($root.'/app/Mine/AGENTS.md', "Written by a person.\n");

    $this->artisan('agents:context', ['--root' => $root])->assertExitCode(0);

    expect(File::get($root.'/app/Mine/AGENTS.md'))->toBe("Written by a person.\n");
});

it('maps only rule files that exist to skills that exist', function (): void {
    $skills = new ReflectionClassConstant(AgentsContextCommand::class, 'SKILLS')->getValue();

    foreach ($skills as $rule => $skill) {
        expect(base_path($rule))->toBeFile()
            ->and(base_path('.ai/skills/'.$skill.'/SKILL.md'))->toBeFile();
    }
});
