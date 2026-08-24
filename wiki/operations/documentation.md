---
title: Documentation
status: current
supersedes: []
code_refs:
    - app/Console/Commands/WikiLintCommand.php
    - app/Support/WikiPage.php
    - tests/Feature/Docs/GuidelinesAreCurrentTest.php
    - .ai/rules/index.md
updated: 2026-08-24
---

# Documentation

Three layers, each with a different job.

| Layer               | Holds                                                      | Read by                        |
| ------------------- | ---------------------------------------------------------- | ------------------------------ |
| `.ai/rules/*.md`    | Path-scoped constraints — short, imperative, always loaded | every agent, on every edit     |
| `wiki/**`           | Explanations — why the code is shaped this way, with proof | on demand, when a page matches |
| `.claude/skills/*/` | Decision procedures for one domain                         | when the domain is entered     |

Rules say what you may not do. The wiki says why. Skills say how to work through
a specific kind of change. A fact belongs in exactly one of them
([[index]]).

## Generated files

`AGENTS.md`, `CLAUDE.md`, `GEMINI.md`, `.cursor/rules/laravel-boost.mdc` and
`.junie/guidelines.md` are all outputs of
`php artisan boost:install --guidelines --skills`, which is wired into
`post-update-cmd` so a Composer update regenerates them. `GEMINI.md` is a copy of
`AGENTS.md`, made in the same hook, because Boost no longer emits it.

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

## When documentation changes

In the same pull request as the code, not in a follow-up. That is the whole reason
the lint blocks ([[operations/contributing]]).
