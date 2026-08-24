---
title: Better defaults from Essentials
status: current
supersedes: []
code_refs:
    - config/essentials.php
    - tests/Pest.php
updated: 2026-08-24
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

Runtime driver choices are separate; see [[operations/runtime]].
