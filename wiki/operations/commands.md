---
title: Commands
status: current
supersedes: []
code_refs:
    - composer.json
    - package.json
    - tests/Feature/Docs/CommandsAreDocumentedTest.php
updated: 2026-08-25
---

# Commands

Every command lives in `composer.json` or `package.json`, and `FEATURES.md` lists
all of them. That last claim is a test, not a promise:
`tests/Feature/Docs/CommandsAreDocumentedTest.php` reads the `scripts` keys of
both manifests and every command class under `app/Console/Commands/`, and fails
naming anything `FEATURES.md` does not mention. The split that matters is
blocking versus reporting ([[architecture/fast-blocking-gates]]).

## Blocking — green on every pull request

| Command                   | Does                                                             |
| ------------------------- | ---------------------------------------------------------------- |
| `composer test`           | Lint, type coverage, PHPStan, and the suite at `--exactly=100.0` |
| `composer test:a11y`      | axe-core at level 3 over every page the kit ships                |
| `composer test:audit`     | `composer audit` plus `bun audit`                                |
| `composer test:dead-code` | PHPStan's dead-code rules over `app/`                            |
| `composer test:deps`      | `composer-unused` — Composer packages nothing requires           |
| `composer test:knip`      | knip — unused frontend files, exports and dependencies           |
| `composer test:wiki`      | `wiki:lint` — dead code refs, dangling links, stale pages        |

## Reporting — scheduled, never blocking

| Command                | Does                                                                             |
| ---------------------- | -------------------------------------------------------------------------------- |
| `composer test:sqlite` | The whole suite against SQLite instead of Postgres, minus vector search, nightly |
| `composer test:mutate` | Mutation score over `app/`, weekly                                               |
| `composer test:evals`  | Grade prompts against real providers, weekly and on request                      |
| `composer sbom`        | Write `sbom.json` (CycloneDX), attached to each release                          |

The default connection is Postgres, because pgvector is where retrieval lives
([[domains/ai-retrieval]]). `test:sqlite` runs the other direction to prove the
kit still boots and passes without it — everything except vector search does.

`test:evals` is the only command here that spends money and the only one allowed
out to a provider. It is excluded from `composer test`, skips itself with no key
configured, and reports rather than blocks, for the reasons in
[[domains/ai-evals]].

## Local loops

| Command                                    | Does                                                      |
| ------------------------------------------ | --------------------------------------------------------- |
| `composer setup`                           | Install, key, migrate, build, from a fresh clone          |
| `composer dev`                             | Server, queue worker, log tail and Vite together          |
| `composer test:fast`                       | Parallel, compact, stops at the first failure             |
| `composer test:dirty`                      | Only the tests covering files you edited                  |
| `composer test:tia-seed`                   | Record the impact map `test:dirty` reads                  |
| `composer lint`                            | Rector, Pint and the frontend formatter, applying fixes   |
| `composer update:requirements`             | Bump PHP and JS dependencies to latest                    |
| `composer typescript:generate`             | Rewrite the generated TypeScript declarations             |
| `php artisan app:make-resource <Name>`     | Scaffold a model and everything around it, tests included |
| `php artisan app:doctor`                   | Check this machine can run, test and build the app        |
| `php artisan wiki:audit`                   | Rewrite `wiki/_meta/audit.json`, the `/document` worklist |
| `php artisan resource:cache`               | Cache the resource registry for production                |
| `php artisan app:sync-permissions`         | Write the permission catalog to the database              |
| `php artisan app:expire-feature-overrides` | Drop feature overrides whose expiry has passed            |
| `php artisan ai:install`                   | Create the pgvector extension, a no-op away from Postgres |
| `php artisan ai:usage`                     | Report AI runs, tokens and spend from the audit log       |

Details per command sit with the thing it operates on:
[[operations/testing]], [[operations/tooling]], [[domains/console-commands]],
[[operations/documentation]].
