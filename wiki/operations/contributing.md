---
title: Contributing
status: current
supersedes: []
code_refs:
    - CONTRIBUTING.md
    - .githooks/commit-msg
    - bin/tamper-guard
    - .githooks/pre-commit
    - .githooks/pre-push
    - bin/recheck-todo
    - bin/recheck-status
    - bin/prove-todo-gate.sh
    - .githooks/post-merge
    - .githooks/sync-deps
    - .githooks/post-checkout
    - .claude/settings.json
    - .claude/hooks/guard-gates.sh
    - .claude/hooks/guard-generated.sh
    - .claude/hooks/require-contract.sh
    - .github/workflows/auditor.yml
    - .github/pull_request_template.md
    - bin/prove-contract-hook.sh
    - .claude/hooks/verify-on-stop.sh
    - bin/worktree-setup
    - tests/bootstrap.php
    - composer.json
    - package.json
    - scripts/agent-setup.sh
    - boost.json
    - .ai/guidelines/general.blade.php
    - .ai/skills/contract/SKILL.md
    - .ai/skills/contract/references/auditor.md
    - .ai/skills/deepen/SKILL.md
    - .ai/skills/outcome/SKILL.md
    - .ai/skills/triage-errors/SKILL.md
    - .claude/agents/auditor.md
    - tests/Feature/Docs/AgentKitIsSelfContainedTest.php
updated: 2026-11-21
---

# Contributing

`CONTRIBUTING.md` is the short version. The parts that surprise people:

## Commit messages are enforced locally

`.githooks/commit-msg` rejects anything that is not a conventional commit before
it reaches a branch. That is not style policing, release-please reads those
prefixes to pick the next version and write the changelog
([[operations/releases]]).

The same hook keeps the plan files current. A `feat` commit (`feat:` or
`feat(scope):`) that stages anything under `app/` or `resources/js/` is refused
unless it also stages a `todo/<area>.status.json`, where the item and the command
that decides it live. A `Todo: none` line in the message skips the check for a
feature that needs no item. Other commit types, and a feat that only touches
tests or docs, are not looked at. The check is one `git diff --cached`, so it adds
well under 0.1s.

## A commit that weakens a gate needs a reason

Before the subject check, `.githooks/commit-msg` runs `bin/tamper-guard`. It
compares the staged content with `HEAD` (during a merge, with every parent) and
refuses a commit that does any of these:

- R1: adds or changes an `ignoreErrors` entry in a `.neon` or `.neon.dist`
  file, which covers `phpstan-deadcode-baseline.neon` too.
- R2: adds `@phpstan-ignore` comments across the staged PHP files.
- R3: adds a `.sloppy-baseline.json` fingerprint or raises its count.
- R4: adds a net `markTestSkipped`, `->skip(`, `->todo(` or `->flaky(` under
  `tests/`, counted on the parsed syntax tree, so a moved test is not a new one.
- R5: lowers or removes a `--min=` or `--exactly=` threshold in a
  `composer.json` script.
- R6: changes the guard, the hook, `bin/prove-control.sh`, the guard's test or
  mutation patches, or the command of roadmap phase `"1"`.

A deliberate change goes through with a `Gate-Change: <reason>` line in the
message. The guard reads the index, never the working tree, so `git commit -a`
is judged on what git is about to record. `tests/Feature/Docs/TamperGuardTest.php`
runs it as a real process against a throwaway repository per case, and the
eight `tests/Mutations/tamper-guard-*.patch` files prove through
`bin/prove-control.sh` that each rule, the hook wiring and the trailer check are
load-bearing. Over the kit's 308 test files a check takes about 1s on an idle
machine.

The `Known limit:` comments in `bin/tamper-guard` name what it leaves out: an
amend is checked only against the commit being amended, and a gate weakened
outside these six rules (the PHPStan level, `phpunit.xml`, a step removed from
`composer verify`) passes. Don't run Pint on the guard by hand. The project's
`mb_str_functions` rule would turn the byte offsets it reads from
`git cat-file --batch` into character offsets. Pre-commit formats `*.php` files
only, so the hooks never do this.

## Format and static checks run before code leaves the machine

`.githooks/pre-commit` fixes the staged files and stages the result: Rector and
Pint for PHP, `vp fmt` and `vp lint --fix` for TypeScript and CSS. A file that
also has unstaged edits is only checked, never rewritten, so it can't pick up
hunks you held back. Whatever the fixers can't repair fails the commit. So does a staged PHP or
TypeScript change that breaks phpstan, `tsc` or a test that maps to it (see the
commit gate below). A clean one-file PHP commit takes about 8s and a
docs-only commit 1s.

`.githooks/pre-push` runs `composer verify`, so CI is a second opinion rather
than the first. The `verify` script in `composer.json` is `composer test` (lint,
Larastan and `tsc`, strict PHPStan, 100% type coverage, the suite at 100%
coverage), then every migration up, down and up again on SQLite, PostgreSQL
and MySQL (`composer test:migrations`, about 7s, and MySQL is skipped when no
server answers), then dead code, unused Composer packages, knip, the Vitest unit
tests (`composer test:js`), the wiki lint, the dependency audit and the
duplicate-code check. A Sloppy report on the pushed
range prints last and never blocks. A full run takes about 113s. Mutation testing stays on demand (`composer test:mutate`).

A green run on a clean tree writes the tree hash to `verified-tree` in the git
directory. The next push skips the gate when the tip of every ref it pushes has that
same tree (older commits in the range are not checked), which is what an amended message, a rebase onto nothing new or a push
to a second remote produces. That skip takes about 1s. A tree that differs from
the stamp only in `todo/*.status.json` counts as stamped too: those files hold no
code, and they are all the background recheck below ever commits. Any other edit,
a missing stamp or a red run brings the full gate back. A push that only deletes
a branch has nothing to verify.

After a full green run the hook also starts `bin/recheck-todo --detach` for the
status files the push touches (none touched, nothing starts) and returns without
waiting for it, so the push takes the same time as before. The recheck
re-runs the command behind every phase in those files (`bin/recheck-status`; a
phase that stops holding gets one more attempt, for its file only) and
commits the files that changed as `chore(todo): recheck statuses`. The next push
carries that commit and, being status-only, takes the 1s skip. Set
`SKIP_TODO_RECHECK=1` to turn it off, or run `bin/recheck-todo` by hand (no `RECHECK_FILES` means every status file,
about 30 minutes). The log
is `recheck-status.log` in the git directory.

Many of those commands run Pest, and two Pest runs on one database fail with
duplicate-table errors (pre-commit and your own `pest` use `testing`, and the
Claude Code stop hook uses `testing_stop`). So the recheck runs Pest commands, and only those, with `DB_DATABASE=testing_recheck`, created from
the `.env` credentials when it is missing, and skips itself if it can't. It also
holds a lock in the git directory (one recheck at a time, a dead pid is
reclaimed), starts only on a clean tree with no commit, rebase or merge in
flight, and commits with `git commit --only` so whatever you have staged is
untouched. It throws the result away if HEAD moved, if a file outside `todo/` and
`wiki/_meta/` changed during the run, or if a status file changed on any line
other than `"status"`. `bin/prove-todo-gate.sh` proves each of these, and the
refusals above, in throwaway clones. Status files stay out of wiki `code_refs`
for the same reason as in [[decisions/agent-dx]]: a recheck commit would
otherwise turn the wiki lint red for a page nobody touched. Pushing a branch other than the one checked out is
refused, because `composer verify` checks the files on disk and would pass a branch
it never looked at; check that branch out and push from there.

A fresh `composer create-project` ends by running `php artisan starter:install`
(the `post-create-project-cmd` script in `composer.json`). It asks which
organization resolver to use and whether to keep the AI layer, prunes what was
declined, and then deletes itself.

`.githooks/post-merge`, and `.githooks/post-checkout` on a branch switch, run
`composer install` or `bun install` when `composer.lock` or `bun.lock` changed,
and `php artisan migrate --graceful` when the change brought new migrations.

`scripts/agent-setup.sh` prepares a fresh checkout for a sandboxed agent. It
copies `.env.example`, installs from both lockfiles (`bun install
--frozen-lockfile`), generates the app key and builds the frontend. It has not
been run in a cloud sandbox yet, so treat it as a starting point.

`package.json` keeps `trustedDependencies` empty: this project grants no
package an install script. Add a package to that list only after reading its
postinstall.

## Claude Code sessions check themselves

`.claude/settings.json` gives every Claude Code session five project hooks.
They read their input with PHP, so they need nothing beyond what the project
already requires.

- `.claude/hooks/format-file.sh` formats each file right after the agent writes it.
- `.claude/hooks/guard-gates.sh` refuses any command that skips git hooks, and
  asks a human before an agent edits gate config: PHPStan, Rector, Pint, knip,
  the Sloppy baseline, `vite.config.ts`, the arch test, the mutation patches in
  `tests/Mutations/`, the workflows, `bin/worktree-setup` and the hooks
  themselves. It asks the same way before an edit to `composer.json`,
  `package.json` or `vet.json`, which hold the gate thresholds and the
  dependency set, and before a command that adds, removes or updates a
  dependency (`composer require`, `bun add` and their siblings). A plain
  `composer install` or `bun install` passes. Once the `contract` skill has frozen its criteria (`.work/feature_list.json`
  exists), it also asks before `.work/contract.md` changes, so an agent cannot
  rewrite the contract to fit the code it wrote. For files it only sees the Edit
  and Write tools. A shell redirect into the same file gets past it, which is
  one reason the git hooks still run the full gate.
- `.claude/hooks/guard-generated.sh` denies an edit to the root `AGENTS.md` and
  to a nested `AGENTS.md` that carries the `agents:context` generated marker. The
  denial names the regenerate command. Like the gate guard, it only sees the Edit
  and Write tools.
- `.claude/hooks/require-contract.sh` lets a session edit one file under `app/`
  freely. It denies the second distinct `app/` file until `.work/feature_list.json`
  exists with at least one item and every item has a non-empty `verified_by`. The
  denial names `.ai/skills/deepen/SKILL.md` and `.ai/skills/contract/SKILL.md`.
  Per-session state sits in `.work/sessions/<session_id>.files` (gitignored) and
  counts only edits that were allowed. It fails open when `php` is missing or
  that directory is not writable. It sees Edit, Write and MultiEdit only: a shell
  redirect or `NotebookEdit` gets past it, so the commit and push gates still
  run for everyone. `bin/prove-contract-hook.sh` proves both directions by piping
  hook JSON into it.
- `.claude/hooks/verify-on-stop.sh` runs PHPStan, the tests touching
  uncommitted files and `tsc` before the agent may report done, and hands any
  failure back to it. It runs once per stop, so an error the agent cannot fix
  does not loop. Its Pest run uses a database of its own, `testing_stop`, so it
  can run while you or pre-commit run Pest on `testing`. `php artisan migrate`
  creates that database when it is missing, and the hook falls back to `testing`
  if it cannot.

The same settings file enables the `php-lsp` and `typescript-lsp` plugins, so
the agent gets go-to-definition, references and type errors from Intelephense
and `typescript-language-server` instead of guessing from grep. `composer setup`
installs both binaries globally with bun. Without them the plugins stay quiet
and nothing else breaks.

## The agent workflows ship with the repo

A developer who clones this with no personal agent setup gets the same
workflows as one who has it. Four skills live in `.ai/skills` and are listed in
`boost.json`, so `boost:install` publishes them to `.claude/skills`,
`.agents/skills` (Codex, Amp, OpenCode), `.cursor/skills` and `.github/skills`
(Copilot) alike:

- `contract` (`.ai/skills/contract/SKILL.md`) writes acceptance criteria with a
  runnable check each, has an independent auditor attack them, freezes them in
  `.work/feature_list.json` and grades the finished work against them. The
  auditor's instructions are `.ai/skills/contract/references/auditor.md`;
  `.claude/agents/auditor.md` is a symlink to that file so Claude Code can run
  it as a subagent, and other agents start a fresh subagent from it.
- `triage-errors` (`.ai/skills/triage-errors/SKILL.md`) goes from the raw error
  (Boost, Sentry, a CI log) to a failing Pest test, then to a fix that turns it
  green.
- `deepen` (`.ai/skills/deepen/SKILL.md`) rewrites a task the way a professional
  in the field would approach it and hands the result to `contract`. `outcome`
  (`.ai/skills/outcome/SKILL.md`) turns a fix into a standing check recorded in
  `todo/outcomes.status.json`.

None of them has to be named. The guidelines in
`.ai/guidelines/general.blade.php` tell the agent to run `deepen` then
`contract` before multi-step work (a one-file change with an obvious test skips
both), `triage-errors` for production errors and `outcome` when someone wants
something to stay true. Each skill's description says when to start it, and none
sets `disable-model-invocation`. Routing is instruction strength: no agent has a
hook that can judge "this task is non-trivial". An agent without skill support
reads the file at the path the guidelines name. They also set the rule behind
the skills: done is decided by a test, an exit code or a number, not by the
model's opinion of its own work.

The one thing enforced for every agent and every human is the commit gate.
`.githooks/pre-commit` runs phpstan when PHP is staged and `tsc --noEmit` when
TypeScript is staged, both over the whole project, because a changed signature
breaks callers that are not staged. Then it runs Pest on the staged test files
plus the mirrored `FooTest.php` of each staged `app/**/Foo.php`, found with
`git ls-files`. When no test maps, it says so and leaves the suite to the push.
Docs-only and deletion-only commits skip all of it. The full suite is the
pre-push gate, so the commit stays fast and the push stays complete.

The only local bypass is skipping the hooks with git's own flag, and
`guard-gates.sh` refuses that for an agent. Nothing stops a human, which is why
the real gate is CI once Actions are on ([[decisions/ci-quality]]).

On a pull request, `.github/workflows/auditor.yml` grades the diff against the
acceptance criteria in the PR body. `.github/pull_request_template.md` has the
table to fill in: an id, the criterion and the command that decides it. The job
is off until the repository variable `AUDITOR_ENABLED` is `true`
(switch-on steps in [[decisions/ci-quality]]).

`tests/Feature/Docs/AgentKitIsSelfContainedTest.php` fails when a hook,
guideline, skill or this page names a slash command or subagent that only
exists in someone's personal `~/.claude`, and when one of the four workflow
skills sets `disable-model-invocation`. The allowlist is Claude Code's own
`/loop` and `/schedule`, plus the app's route prefixes such as `/admin`. It
also fails when a Codex hook command starts from one machine's path instead of
`$(git rev-parse --show-toplevel)`, and when code carries a personal plugin's
`ponytail:` comment prefix: a deliberate shortcut is marked `Known limit:`.

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

`tests/bootstrap.php` also gives every pest process that is not a parallel worker
its own browser state file, through `PEST_BROWSER_STATE_FILE` in the system temp
directory, because pest-plugin-browser otherwise keeps one file per checkout and
a second run (a targeted run beside the gate, say) would delete it from under the
gate's workers. Workers inherit the path. The same file clears the provider keys
before the framework boots, except when `--evals` is on the command line.

Vite needed nothing: each worktree has its own `public/hot`, and the dev server
takes the next free port when 5173 is busy. Removing a worktree does not drop its
databases; drop `starter_<worktree>` and `testing_<worktree>` by hand.

## Tests are not optional

Every change is programmatically tested: a new test, or an updated one, then run
the affected tests. The coverage gate is `--exactly=100.0`, so untested new code
fails and so does dead code ([[operations/testing]]).

While working, `composer test:fast` (parallel, compact, stops at the first
failure) or `composer test:dirty` (only tests covering edited files) are the local
loops, and `composer test:ai` runs the AI layer's tests with `tests/Unit/ArchTest.php`.
`composer test` is the gate for the suite; `composer verify` is the whole push gate.

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
something that fails, `tests/Feature/CrossOrgLeakTest.php` case by case,
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

`AGENTS.md`, `resources/js/types/generated.d.ts` and `CHANGELOG.md` are all
outputs. Change the source and regenerate; a test fails when they drift
([[operations/documentation]], [[architecture/type-safety]]). `CLAUDE.md` is the
one line `@AGENTS.md`, so Claude Code reads the same file as every other agent.

## Documentation is part of the change

Rewriting the documentation a refactor invalidated belongs in the same pull
request as the refactor, not in a follow-up that never happens. The wiki lint is a
blocking gate for that reason ([[operations/documentation]]).
