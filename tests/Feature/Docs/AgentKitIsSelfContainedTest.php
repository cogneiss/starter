<?php

declare(strict_types=1);

use Illuminate\Routing\Route as RouteDefinition;
use Illuminate\Support\Facades\Route;

/**
 * Claude Code ships these, so a fresh clone has them without any personal
 * setup. Everything else an agent file names must be defined in this repo.
 *
 * @var list<string>
 */
const CLAUDE_CODE_BUILT_INS = ['loop', 'schedule'];

/**
 * The files an agent reads as instructions: hooks, guidelines, project skills,
 * project commands, and the contributing page that explains them.
 *
 * @return array<string, string>
 */
function agentInstructionFiles(): array
{
    $files = [
        ...glob(base_path('.claude/hooks/*.sh')) ?: [],
        ...glob(base_path('.codex/hooks/*.sh')) ?: [],
        ...glob(base_path('.ai/guidelines/*.blade.php')) ?: [],
        ...glob(base_path('.ai/skills/*/SKILL.md')) ?: [],
        ...glob(base_path('.claude/commands/*.md')) ?: [],
        base_path('wiki/operations/contributing.md'),
    ];

    return collect($files)
        ->mapWithKeys(fn (string $file): array => [str_replace(base_path().'/', '', $file) => (string) file_get_contents($file)])
        ->all();
}

it('defines every slash command an agent file names', function (): void {
    $routePrefixes = collect(Route::getRoutes()->getRoutes())
        ->map(fn (RouteDefinition $route): string => explode('/', $route->uri())[0])
        ->unique()
        ->all();

    $unresolved = collect(agentInstructionFiles())
        ->flatMap(function (string $contents, string $file): array {
            preg_match_all('#(?<=[\s`(])/([a-z][a-z0-9-]+)(?=[\s`).,:])#', $contents, $matches);

            return array_map(fn (string $name): string => $name.' ('.$file.')', array_unique($matches[1]));
        })
        ->reject(function (string $entry) use ($routePrefixes): bool {
            $name = strtok($entry, ' ');

            return in_array($name, CLAUDE_CODE_BUILT_INS, true)
                || in_array($name, $routePrefixes, true)
                || is_file(base_path(".ai/skills/{$name}/SKILL.md"))
                || is_file(base_path(".claude/commands/{$name}.md"));
        })
        ->values()
        ->all();

    expect($unresolved)->toBe([], sprintf(
        'These slash commands resolve only on a machine with personal Claude Code setup: %s. Add a skill under .ai/skills (and to boost.json) or a command under .claude/commands.',
        implode(', ', $unresolved),
    ));
});

it('defines every subagent a project skill hands work to', function (): void {
    $missing = collect(glob(base_path('.ai/skills/*/SKILL.md')) ?: [])
        ->flatMap(function (string $file): array {
            preg_match_all('/`([a-z][a-z0-9-]+)` subagent/', (string) file_get_contents($file), $matches);

            return $matches[1];
        })
        ->unique()
        ->reject(fn (string $name): bool => is_file(base_path(".claude/agents/{$name}.md")))
        ->values()
        ->all();

    expect($missing)->toBe([], sprintf(
        'Project skills hand work to subagents this repo does not define: %s. Add them under .claude/agents.',
        implode(', ', $missing),
    ));
});

/**
 * Whether the frontmatter lets the model start the skill on its own.
 */
function modelCanStart(string $skill): bool
{
    preg_match('/\A---\n(.*?)\n---/s', $skill, $frontmatter);

    return preg_match('/^disable-model-invocation:\s*true\s*$/m', $frontmatter[1] ?? '') !== 1;
}

it('lets the model start every workflow skill the guidelines route to', function (): void {
    $blocked = collect(['contract', 'deepen', 'outcome', 'triage-errors'])
        ->reject(fn (string $name): bool => modelCanStart((string) file_get_contents(base_path(".ai/skills/{$name}/SKILL.md"))))
        ->values()
        ->all();

    expect($blocked)->toBe([], sprintf(
        'The guidelines tell the agent to start these itself, but their frontmatter has disable-model-invocation: true: %s.',
        implode(', ', $blocked),
    ));
});

it('detects a skill the model cannot start', function (): void {
    expect(modelCanStart("---\nname: x\ndisable-model-invocation: true\n---\n# X"))->toBeFalse()
        ->and(modelCanStart("---\nname: x\ndescription: y\n---\n# X"))->toBeTrue();
});
