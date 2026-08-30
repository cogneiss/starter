---
title: Contributing
status: current
supersedes: []
code_refs:
    - CONTRIBUTING.md
    - .githooks/commit-msg
updated: 2026-08-31
---

# Contributing

`CONTRIBUTING.md` is the short version. The parts that surprise people:

## Commit messages are enforced locally

`.githooks/commit-msg` rejects anything that is not a conventional commit before
it reaches a branch. That is not style policing — release-please reads those
prefixes to pick the next version and write the changelog
([[operations/releases]]).

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
