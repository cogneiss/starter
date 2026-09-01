---
title: Better defaults from Essentials
status: current
supersedes: []
code_refs:
    - config/essentials.php
    - tests/Pest.php
updated: 2026-09-01
---

# Better defaults from Essentials

`nunomaduro/essentials` turns on the settings most Laravel applications end up
enabling by hand, configured in `config/essentials.php`.

On by default:

- Strict models — no lazy loading, no silently discarded attributes.
- Automatic eager loading, and aggressive prefetching.
- Immutable dates.
- Unguarded models (the form requests are the boundary, not `$fillable`).
- Destructive command prohibition in production.
- Forced HTTPS outside local.
- A default password for factories.
- Blocked stray HTTP requests and faked sleep in tests.

## Why these and not a curated subset

Each one converts a class of runtime surprise into an immediate failure: an N+1
becomes an exception in development instead of a slow page in production, a typo
in an attribute name throws instead of being dropped, a `migrate:fresh` against
production is refused. The cost of each is a stricter local loop, which is where
you want the cost.

Two of them shape how tests are written: stray HTTP is blocked and sleep is
faked. `tests/Pest.php` extends that with frozen time and blocked stray process
calls, so a test that reaches the network or the shell fails loudly rather than
becoming flaky later. When a test genuinely needs a subprocess — rendering the
agent guideline files, for instance — it constructs the Symfony process directly
rather than going through the facade the ban applies to.

## The same default, extended to providers

Blocked stray HTTP does not cover an AI provider, which speaks through the SDK's
gateway rather than the `Http` facade, so `tests/Pest.php` adds the equivalent:
every agent under `app/Ai/Agents` is faked before each test in `Feature/Ai` with
`preventStrayPrompts()`, no scripted answer attached. A test that forgets its own
fake throws instead of dialling a provider and spending money. It is central for
the same reason the HTTP ban is — the per-file version is the one someone forgets
in the file that matters.

## Shared setup lives in the same file

`tests/Pest.php` also holds the helpers a whole area of the suite would
otherwise copy: `resourceSearchDefects()` judges every resource definition's
`searchable()`, `sortable()` and `recordLabel()` against the real schema,
`motionStyles()` runs the shipped TypeScript motion module through Bun and
reports what it emits, and `ownerBeforeOnboarding()`, `uploadedImport()` and
`runImport()` build the states the onboarding and import tests start from. Each
one is central for the reason the bans are: the per-file copy is the one that
drifts.

`motionStyles()` constructs the Symfony process directly, because the facade is
faked for the whole suite and this helper genuinely has to run a program.

`tests/Evals/` is the deliberate exception, and the only one: that suite is
grouped `evals`, excluded from `composer test`, and skips itself when no key is
configured ([[domains/ai-evals]]).

Runtime driver choices are separate; see [[operations/runtime]].
