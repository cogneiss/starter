---
name: testing-gates
description: "Use when running, fixing or adding tests in this starter kit, or when a gate is red and you need to know which command to run and what its failure means. Covers the local loop (test:fast, test:dirty, filters), the blocking gates on every pull request (composer test at --exactly=100.0, a11y, audits, dead code, unused deps, knip, wiki:lint), how to record a finding in the right baseline, and the standing rule that a threshold is never lowered to get green."
license: MIT
metadata:
    author: cogneiss
---

# Testing and the gates

## Which command, when

| Situation                           | Run                                                              |
| ----------------------------------- | ---------------------------------------------------------------- |
| Iterating on one file               | `php artisan test --compact --filter=<TestName>`                 |
| Iterating, broader                  | `composer test:fast` (parallel, compact, stops at first failure) |
| After a focused change              | `composer test:dirty` (only tests covering edited files)         |
| Before pushing                      | `composer test`                                                  |
| Touched a page, or any UI           | `composer test:a11y`                                             |
| Touched a file some wiki page cites | `composer test:wiki`, and run `/document`                        |

`composer test:dirty` reads an impact map. If it looks wrong or has never been
built here, rebuild it with `composer test:tia-seed` (needs a coverage driver).
Test Impact Analysis is local only — `tests/Pest.php` calls
`pest()->tia()->locally()`. Never add `--dirty` or `--tia` to a workflow.

`composer test` is the aggregate: `test:lint` (Pint, Rector dry-run, frontend
formatter), `test:type-coverage` (`--min=100`), `test:types` (PHPStan plus
`tsc --noEmit`), then `test:unit` — Pest parallel with coverage at
`--exactly=100.0`.

Coverage needs a driver. Through Herd:

```bash
herd coverage composer test:unit
```

## What each blocking gate means when it goes red

| Gate                      | Red means                                                         |
| ------------------------- | ----------------------------------------------------------------- |
| `composer test`           | lint, types, type coverage or the suite; read the first failure   |
| `--exactly=100.0`         | either new untested code, **or** code nothing reaches             |
| `composer test:a11y`      | axe-core at level 3 found a violation on a page the kit ships     |
| `composer test:audit`     | `composer audit` / `bun audit` found a known advisory             |
| `composer test:dead-code` | PHPStan found unreachable code in `app/`                          |
| `composer test:deps`      | a Composer package nothing requires                               |
| `composer test:knip`      | unused frontend file, export or dependency                        |
| `composer test:wiki`      | a wiki page cites a file that moved, or one that changed under it |

Coverage is exact, not a floor. Over 100 is impossible; under it is untested
code; a line nothing reaches also fails, which is why the coverage gate doubles
as a dead-code gate. Delete the unreachable code rather than writing a test that
reaches it artificially.

## The standing rule

**Never lower a threshold to make a gate pass.** Not `--exactly=100.0`, not
`--min=100`, not the mutation `--min=80`. A loosened threshold silently stops
protecting everything else it covered, and nobody raises it back.

When a finding is genuinely acceptable, record it in that tool's own baseline
with a comment saying why:

- PHPStan dead code — `phpstan-deadcode-baseline.neon`
- unused Composer packages — `composer-unused.php`
- knip — `knip.json`
- convention guards — `config/conventions.php`, keyed by class, reason as the value
- one-gate policy method — the `$exceptions` array in
  `tests/Unit/AuthorizationConventionTest.php`

A baseline entry is reviewable in a diff. A changed threshold is not.

## How tests run here

`tests/Pest.php` and `tests/TestCase.php` set the rules:

- Parallel by default. Anything relying on shared global state will flake.
- Time is frozen and `sleep` is faked.
- Stray HTTP is blocked, and so are stray processes
  (`Process::preventStrayProcesses()`). A test that reaches the network fails now
  rather than becoming a CI-only flake.
- Faking a process command: the array form renders quoted, so patterns need
  quoted-form globs — `Process::fake(["*'log'*" => ...])`, not `'git log*'`.
- A test that genuinely needs a subprocess builds the Symfony process directly
  and says why in a comment.

Scoped models need an organization bound — `runAs()`, see the `org-access` skill.
Use factory states before hand-building a model; `UserFactory` ships
`unverified()`, `withoutTwoFactor()` and `forOrganization()`.

## Writing a new test

- `php artisan make:test --pest SomeFeatureTest` — feature tests by default,
  `--unit` for unit.
- One behaviour per test, named as a sentence: `it('...')`.
- Assert the negative case for anything authorization- or tenancy-related.
- New code with no test fails the coverage gate. Write the test in the same
  change, not in a follow-up.

## Scheduled, never blocking

`composer test:sqlite` (nightly, the suite on SQLite minus the `pgvector` group —
SQLite and Postgres disagree on UUID keys, JSON columns, unordered `ORDER BY`,
case-sensitive `LIKE`), `composer test:mutate` (weekly) and `composer test:evals`
(weekly, the only command allowed out to a provider; it skips itself with no key
set). A mutation score moves when nobody touched
it, so gating on it would teach people to write tests that satisfy the mutator.
Promoting either to a required check means making it deterministic first.

## Why it is shaped this way

- `wiki/operations/testing.md` — the suite, the gates, the local loops
- `wiki/architecture/fast-blocking-gates.md` — why blocking versus scheduled
- `wiki/operations/ci.md` — the job topology
- `wiki/operations/commands.md` — every command in one table
