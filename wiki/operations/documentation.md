---
title: Documentation
status: current
supersedes: []
code_refs:
    - app/Console/Commands/WikiLintCommand.php
    - app/Console/Commands/WikiAuditCommand.php
    - app/Support/WikiPage.php
    - app/Support/GitLog.php
    - .claude/commands/document.md
    - tests/Feature/Docs/GuidelinesAreCurrentTest.php
    - .ai/rules/index.md
    - config/boost.php
    - boost.json
    - app/Console/Commands/AgentsContextCommand.php
    - tests/Feature/Docs/NestedAgentContextTest.php
    - .claude/hooks/guard-generated.sh
updated: 2026-11-20
---

# Documentation

Three layers, each with a different job.

| Layer            | Holds                                                      | Read by                        |
| ---------------- | ---------------------------------------------------------- | ------------------------------ |
| `.ai/rules/*.md` | Path-scoped constraints — short, imperative, always loaded | every agent, on every edit     |
| `wiki/**`        | Explanations — why the code is shaped this way, with proof | on demand, by `code_refs`      |
| `.ai/skills/*/`  | Decision procedures for one domain                         | when the domain is entered     |

Rules say what you may not do. The wiki says why. Skills say how to work through
a specific kind of change. A fact belongs in exactly one of them
([[index]]).

Skills are authored in `.ai/skills/<name>/SKILL.md` and published into
`.claude/skills/*/` alongside the vendor packs by
`php artisan boost:install --skills`. Edit the source, not the published copy.
Twelve are first-party (the directories in `.ai/skills`): `resource-spine`, `org-access`, `testing-gates`,
`ai-layer`, `ux-kit`, `attack-review`, `platform-ops`, `database-design`, and the four workflow skills `contract`, `deepen`, `outcome`
and `triage-errors` ([[operations/contributing]]). Publishing writes a symlink per agent directory, `.claude` and
`.agents`, and both are committed, so a clone gets the pack without running
Boost first.

`.ai/rules/index.md` maps globs to rule files, and its row is what makes a rule
load at all. A rule file with no row is a file nobody reads: `app/Ai/**`,
`app/Mcp/**`, `config/ai.php` and `tests/Evals/**` route to `.ai/rules/ai.md`.

The rows are paths, not subsystems, which is why `.ai/rules/ux.md` needs five of
them — `app/Support/ResourceQuery.php`, `app/Http/Controllers/Concerns/**`,
`app/Onboarding/**`, `app/Imports/**` and the two frontend globs. A UX layer that
reaches across the request cycle, the queue and the browser has no single
directory to name, and a row that named only one of them would leave the rest
editable without the rule ever loading ([[domains/ux-list-kit]]).
`.ai/rules/platform-ops.md` has the same shape for the same reason: the API
routes, webhooks, admin area and provider seams span `routes/`, `app/` and
`config/`, so its row names each of them ([[operations/ops-read-api]]).

## Generated files

`AGENTS.md` is the output of
`php artisan boost:install --guidelines --skills`, which is wired into
`post-update-cmd` so a Composer update regenerates it. `CLAUDE.md` is the one
line `@AGENTS.md`. `agents.claude_code.guidelines_path` in `config/boost.php`
keeps it that way: without it Boost renders the block into `CLAUDE.md` whenever
that file exists.

`config/boost.php` pins `enforce_tests` to `true`. Left unset, Boost decides
whether to emit its test-enforcement guideline by running
`artisan test --list-tests` in a subprocess and counting the results, so the
generated files change depending on whether that subprocess succeeded — and the
drift gate below then fails on a diff that touched none of this. A blocking gate
that goes red for unrelated reasons is a gate that gets switched off, so the
value is pinned rather than detected.

`tests/Feature/Docs/GuidelinesAreCurrentTest.php` fails when it drifts, or when
`CLAUDE.md` stops being the one-line import. It proves
drift _inside_ the `<laravel-boost-guidelines>` block; a check over the whole file
would pass on a stale block and be a false negative. It runs Boost in a real
subprocess with `APP_ENV=local`, because Boost disables itself under
`runningUnitTests()`.

## Trimmed root, nested files

The root file loads on every turn, so it keeps only what every task needs: each
layer's head in `.ai/guidelines` is a pointer to its rule file and skill plus one
or two never-break lines. Procedure lives in the skills and rules and is cited,
not repeated. `config/boost.php` also drops the `deployments`, `wayfinder/core`
and `spatie/laravel-activitylog/core` sections, because their skills carry the
same procedure. The Inertia and Boost sections stay: the Inertia v3 notes are
not in any skill. Versions are never written in a guideline; `composer.lock` is
the source.

`php artisan agents:context` reads the table in `.ai/rules/index.md` and writes
an `AGENTS.md` into every directory a `dir/**` glob names. Each lists the rule
files whose globs match that directory and the skill to load. `--check` fails
without writing, and `tests/Feature/Docs/NestedAgentContextTest.php` runs it so
the files cannot drift from the index.

Only `AGENTS.md` is nested. Claude Code already scopes `.claude/rules`, which is
a symlink to `.ai/rules`, by each rule's `paths:` frontmatter, so a nested
`CLAUDE.md` would load the same text twice. `boost.json` configures Claude Code, Amp, Codex and
OpenCode; which of them read nested `AGENTS.md` is not documented here. `app` and `wiki` get no
file: `app.md` would repeat over every child, and the wiki lint reads every
markdown file under `wiki`. A nested file without the generated marker is
hand-written and left alone.

`.claude/hooks/guard-generated.sh` denies Edit, Write and MultiEdit on the
generated files and names the regenerate command. Nested files are recognised by
the marker line.

`resources/js/types/generated.d.ts` is generated too
([[architecture/type-safety]]), and so is `CHANGELOG.md`
([[operations/releases]]). Never hand-edit any of them: change the source and
regenerate.

## The wiki lint

`composer test:wiki` runs `php artisan wiki:lint`, a blocking gate with five
rules. `wiki/_meta/lint.md` documents each rule and how to fix it, including the
failure mode to watch: a stale page can be silenced by bumping `updated:` alone,
which clears the gate and hides the drift.

## The documentation loop

`php artisan wiki:audit` writes `wiki/_meta/audit.json`: a worklist of stale
pages, application files no page lists in `code_refs`, and pages whose refs have
all been deleted. The nested `AGENTS.md` files under `app/` are left off the
undocumented list, because `agents:context` generates them and no page explains
them (`app/Console/Commands/WikiAuditCommand.php`). `.githooks/post-commit`
refreshes it after every commit and `app:doctor` prints the counts, so the work
surfaces without being looked for.
The file is generated and git-ignored — regenerate it, never edit it.

Agents do not load the wiki by default. A page is opened when the task touches a
file in its `code_refs`. After a change, only the pages whose `code_refs` include
a changed file are reread. No command lists those pages for uncommitted edits;
`rg -l '<path>' wiki` finds them. After a commit, the `stale` entries in
`wiki/_meta/audit.json` are exactly that list, and `wiki:lint` blocks on them.

`/document` (`.claude/commands/document.md`) is the other half: it reads the
worklist, rereads the code, and rewrites the pages. No language model runs in
CI, so there is no key in the pipeline and no per-push cost. CI produces the
worklist; a developer's machine writes the prose.

The split between the two commands is deliberate:

| Command      | Blocks | Because                                                                                                                                     |
| ------------ | ------ | ------------------------------------------------------------------------------------------------------------------------------------------- |
| `wiki:lint`  | Yes    | A page that contradicts the code is a wrong claim, the same class of failure as a failing test                                              |
| `wiki:audit` | No     | Blocking "this new file has no page" buys one-line pages written to clear the gate, and unlike staleness there is no wrong claim to correct |

## When documentation changes

In the same pull request as the code, not in a follow-up. That is the whole reason
the lint blocks ([[operations/contributing]]).
