---
title: Testing
status: current
supersedes: []
code_refs:
    - tests/Pest.php
    - tests/TestCase.php
    - tests/Unit/ArchTest.php
    - tests/Browser/AccessibilityTest.php
updated: 2026-08-24
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
  non-final classes and insecure calls.
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

The suite reruns against `postgres:17` nightly, because development and CI use
SQLite while most forks deploy Postgres, and the two disagree on UUID keys, JSON
columns, unordered `ORDER BY` and case-sensitive `LIKE`. Mutation testing runs
weekly and never blocks: coverage says a line ran, the mutation score says a test
failed when that line was broken, and gating on the score teaches people to write
tests that satisfy the mutator ([[operations/ci]]).
