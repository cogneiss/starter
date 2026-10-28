---
title: Contributing
status: current
supersedes: []
code_refs:
    - CONTRIBUTING.md
    - .githooks/commit-msg
    - .githooks/pre-commit
    - .githooks/pre-push
    - .githooks/post-merge
    - .githooks/sync-deps
    - .githooks/post-checkout
    - .claude/settings.json
    - .claude/hooks/guard-gates.sh
    - .claude/hooks/verify-on-stop.sh
    - bin/worktree-setup
    - tests/bootstrap.php
    - composer.json
updated: 2026-10-29
---

# Contributing

`CONTRIBUTING.md` is the short version. The parts that surprise people:

## Commit messages are enforced locally

`.githooks/commit-msg` rejects anything that is not a conventional commit before
it reaches a branch. That is not style policing — release-please reads those
prefixes to pick the next version and write the changelog
([[operations/releases]]).

## Format and static checks run before code leaves the machine

`.githooks/pre-commit` fixes the staged files and stages the result: Rector and
Pint for PHP, `vp fmt` and `vp lint --fix` for TypeScript and CSS. A file that
also has unstaged edits is only checked, never rewritten, so it can't pick up
hunks you held back. Whatever the fixers can't repair fails the commit.

`.githooks/pre-push` runs the whole gate, so CI is a second opinion rather than
the first: `composer test` (lint, Larastan and `tsc`, strict PHPStan, 100% type
coverage, the suite at 100% coverage), then dead code, unused Composer packages,
knip, the wiki lint, the dependency audit and the duplicate-code check. A Sloppy
report on the pushed range prints last and never blocks. It takes a few
minutes. Mutation testing stays on demand (`composer test:mutate`).

`.githooks/post-merge`, and `.githooks/post-checkout` on a branch switch, run
`composer install` or `bun install` when `composer.lock` or `bun.lock` changed,
and `php artisan migrate --graceful` when the change brought new migrations.

## Claude Code sessions check themselves

`.claude/settings.json` gives every Claude Code session three project hooks.
They read their input with PHP, so they need nothing beyond what the project
already requires.

- `.claude/hooks/format-file.sh` formats each file right after the agent writes it.
- `.claude/hooks/guard-gates.sh` refuses any command that skips git hooks, and
  asks a human before an agent edits gate config: PHPStan, Rector, Pint, knip,
  the Sloppy baseline, `vite.config.ts`, the arch test, the mutation patches in
  `tests/Mutations/`, the workflows, `bin/worktree-setup` and the hooks
  themselves. Once `/contract` has frozen its criteria (`.work/feature_list.json`
  exists), it also asks before `.work/contract.md` changes, so an agent cannot
  rewrite the contract to fit the code it wrote. It only sees the Edit and Write
  tools. A shell redirect into the same file gets past it, which is one reason
  the git hooks still run the full gate.
- `.claude/hooks/verify-on-stop.sh` runs PHPStan, the tests touching
  uncommitted files and `tsc` before the agent may report done, and hands any
  failure back to it. It runs once per stop, so an error the agent cannot fix
  does not loop.

The same settings file enables the `php-lsp` and `typescript-lsp` plugins, so
the agent gets go-to-definition, references and type errors from Intelephense
and `typescript-language-server` instead of guessing from grep. `composer setup`
installs both binaries globally with bun. Without them the plugins stay quiet
and nothing else breaks.

## Each worktree gets its own database

Agents working in parallel (`claude --worktree`, `git worktree add`) would
otherwise share one `.env`, one schema and one `testing` database, and a
`RefreshDatabase` run in one worktree wipes the tables another is using.
`.githooks/post-checkout` spots a brand-new linked worktree (the old head is the
null sha) and runs `bin/worktree-setup`. That copies `.env` from the main
checkout, points `DB_DATABASE` at `starter_<worktree>`, installs, migrates that
database and `testing_<worktree>`, and builds the assets. `tests/bootstrap.php`
sends the suite to `testing_<worktree>` whenever `.git` is a file, and leaves an
explicit `DB_DATABASE` alone (the `:memory:` of `composer test:sqlite`, say).
Parallel runs append `_test_N` to that name as usual.

Vite needed nothing: each worktree has its own `public/hot`, and the dev server
takes the next free port when 5173 is busy. Removing a worktree does not drop its
databases; drop `starter_<worktree>` and `testing_<worktree>` by hand.

## Tests are not optional

Every change is programmatically tested: a new test, or an updated one, then run
the affected tests. The coverage gate is `--exactly=100.0`, so untested new code
fails and so does dead code ([[operations/testing]]).

While working, `composer test:fast` (parallel, compact, stops at the first
failure) or `composer test:dirty` (only tests covering edited files) are the local
loops. `composer test` is the gate.

## Three AI-layer rules are in `CONTRIBUTING.md` rather than only in the wiki

Touching `app/Ai` carries rules whose failure mode is not a red test on your
branch: a tool that skips `authorizeFor()` returns another member's rows and
renders them correctly, a test that forgets its fake spends real money, and
unfenced text hands a stored record the ability to give instructions. Those three
are stated in `CONTRIBUTING.md` because a contributor reads that file before
their first pull request and the wiki only when something already went wrong
([[domains/ai-layer-overview]]).

Each is backed by something that fails: `tests/Unit/ArchTest.php` for the
read-only tool rule, `tests/Pest.php` for the provider ban, and the fence's own
tests. The prose exists so the failure is recognisable, not to replace it.

## Three UX-layer rules are in `CONTRIBUTING.md` for the same reason

Lists, forms and copy have the same shape of failure as the AI layer: nothing
goes red on the branch that introduced it. A list that filters a collection
instead of scoping a query renders another organization's rows correctly, a rule
restated in TypeScript passes until the server disagrees, and a hex literal in a
component looks right in whichever organization the author happened to be in.

So `CONTRIBUTING.md` states three: list through
`App\Http\Controllers\Concerns\ListsResources` so a foreign id is a 404 rather
than a 403, validate through the form request Precognition already checks
against, and take copy, colour and duration from `lang/`, the brand tokens and
`resources/js/lib/motion.ts` rather than from the component. Each is backed by
something that fails — `tests/Feature/CrossOrgLeakTest.php` case by case,
`tests/Feature/PrecognitionParityTest.php` on drift, and the locale key-set test
([[domains/ux-list-kit]]).

## Formatting is not a review topic

`composer lint` applies Rector, Pint and the frontend formatter. On a branch push
in this repository, the autofix bot does it and commits the result back, so a
formatting nit never costs a review round
([[operations/ci]]).

## If a gate fails

Fix the code, or record the finding in the tool's own baseline with a comment
saying why. Never lower a threshold ([[architecture/fast-blocking-gates]]).

## Generated files are never hand-edited

`AGENTS.md`, `CLAUDE.md`, `GEMINI.md`, `.cursor/rules/laravel-boost.mdc`,
`.junie/guidelines.md`, `resources/js/types/generated.d.ts` and `CHANGELOG.md` are
all outputs. Change the source and regenerate; a test fails when they drift
([[operations/documentation]], [[architecture/type-safety]]).

## Documentation is part of the change

Rewriting the documentation a refactor invalidated belongs in the same pull
request as the refactor, not in a follow-up that never happens. The wiki lint is a
blocking gate for that reason ([[operations/documentation]]).
