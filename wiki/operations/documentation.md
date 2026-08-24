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
updated: 2026-08-24
---

# Documentation

Three layers, each with a different job.

| Layer            | Holds                                                      | Read by                        |
| ---------------- | ---------------------------------------------------------- | ------------------------------ |
| `.ai/rules/*.md` | Path-scoped constraints — short, imperative, always loaded | every agent, on every edit     |
| `wiki/**`        | Explanations — why the code is shaped this way, with proof | on demand, when a page matches |
| `.ai/skills/*/`  | Decision procedures for one domain                         | when the domain is entered     |

Rules say what you may not do. The wiki says why. Skills say how to work through
a specific kind of change. A fact belongs in exactly one of them
([[index]]).

Skills are authored in `.ai/skills/<name>/SKILL.md` and published into
`.claude/skills/*/` alongside the vendor packs by
`php artisan boost:install --skills`. Edit the source, not the published copy.
Three are first-party: `resource-spine`, `org-access`, `testing-gates`.

## Generated files

`AGENTS.md`, `CLAUDE.md`, `GEMINI.md`, `.cursor/rules/laravel-boost.mdc` and
`.junie/guidelines.md` are all outputs of
`php artisan boost:install --guidelines --skills`, which is wired into
`post-update-cmd` so a Composer update regenerates them. `GEMINI.md` is a copy of
`AGENTS.md`, made in the same hook, because Boost no longer emits it.

`config/boost.php` pins `enforce_tests` to `true`. Left unset, Boost decides
whether to emit its test-enforcement guideline by running
`artisan test --list-tests` in a subprocess and counting the results, so the
generated files change depending on whether that subprocess succeeded — and the
drift gate below then fails on a diff that touched none of this. A blocking gate
that goes red for unrelated reasons is a gate that gets switched off, so the
value is pinned rather than detected.

`tests/Feature/Docs/GuidelinesAreCurrentTest.php` fails when they drift. It proves
drift _inside_ the `<laravel-boost-guidelines>` block; a check over the whole file
would pass on a stale block and be a false negative. It runs Boost in a real
subprocess with `APP_ENV=local`, because Boost disables itself under
`runningUnitTests()`.

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
all been deleted. `.githooks/post-commit` refreshes it after every commit and
`app:doctor` prints the counts, so the work surfaces without being looked for.
The file is generated and git-ignored — regenerate it, never edit it.

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
