---
title: Testing
status: current
supersedes: []
code_refs:
    - tests/Pest.php
    - tests/TestCase.php
    - tests/Unit/ArchTest.php
    - tests/Browser/AccessibilityTest.php
updated: 2026-08-25
---

# Testing

Pest 5, covering every controller, action, rule and middleware.

## The gates

- **Coverage at `--exactly=100.0`** (`composer test:unit`). An exact match, not a
  minimum: new untested code fails, and so does code nothing reaches. That second
  half is why the coverage gate doubles as a dead-code gate.
- **100% type coverage** (`composer test:type-coverage`).
- **Architecture presets** — php, strict, laravel, security — in
  `tests/Unit/ArchTest.php`, catching `dd()` leftovers, loose comparisons,
  non-final classes and insecure calls. The AI layer adds four rules of its own
  there: tools never touch `DB` or `ConsumeConfirmToken` (with `ProposeAction`
  the one named exemption), and every agent implements `OrganizationScoped` and
  `HasMiddleware` and never reaches for `DB`, the confirm-token action or the
  invitation action ([[domains/ai-agents-and-tools]]).
- **Accessibility** — `tests/Browser/AccessibilityTest.php` runs every page the
  kit ships through axe-core with `assertNoAccessibilityIssues(level: 3)`, so
  minor and best-practice violations fail too. A starter kit's accessibility
  defects are inherited by every application built on it, which is why this one is
  blocking rather than advisory.

## How tests run

Parallel, with frozen time, faked sleep, and stray HTTP and process calls blocked
by default — `tests/Pest.php` and `tests/TestCase.php`. A test that reaches the
network fails immediately instead of becoming a flake that only fails in CI.

When a test genuinely needs a subprocess, it constructs the Symfony process
directly rather than going through the facade the ban covers, and says why in a
comment.

The same ban covers providers. `tests/Pest.php` walks every class in
`app/Ai/Agents` before each test under `Feature/Ai` and calls
`Ai::fakeAgent($agent)->preventStrayPrompts()` with no scripted answer, so a test
that forgets to script its own fake throws rather than dialling out. Central
rather than per-file, because the failure mode of forgetting is a real charge on
a real key ([[domains/ai-evals]]).

Scoped models need an organization bound: use `resolve(OrganizationContext::class)->runAs()`
([[domains/multi-tenancy]]). Use factory states before hand-building a model —
`UserFactory` ships `unverified()` and `withoutTwoFactor()`, and `db:seed` creates
`test@example.com` / `password` for local sign-in.

## Local loops

| Command                  | Runs                                          |
| ------------------------ | --------------------------------------------- |
| `composer test:fast`     | parallel, compact, stops at the first failure |
| `composer test:dirty`    | only tests covering files you edited          |
| `composer test:tia-seed` | records the impact map `test:dirty` reads     |

Test Impact Analysis is enabled locally only — `tests/Pest.php` calls
`pest()->tia()->locally()`. CI runs everything
([[architecture/fast-blocking-gates]]).

## Scheduled runs

`composer test:sqlite` reruns the suite on SQLite in memory nightly, minus the
`pgvector` group. Postgres is the default everywhere now
([[operations/runtime]]), so the scheduled run checks the other direction: that a
fork which wants no Postgres still gets everything except vector search. The two
engines disagree on UUID keys, JSON columns, unordered `ORDER BY` and
case-sensitive `LIKE`, which is what that run catches. Mutation testing runs
weekly and never blocks: coverage says a line ran, the mutation score says a test
failed when that line was broken, and gating on the score teaches people to write
tests that satisfy the mutator ([[operations/ci]]).
