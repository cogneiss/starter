---
title: Commands
status: current
supersedes: []
code_refs:
    - composer.json
    - package.json
    - tests/Feature/Docs/CommandsAreDocumentedTest.php
updated: 2026-08-24
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

| Command                | Does                                                        |
| ---------------------- | ----------------------------------------------------------- |
| `composer test:pgsql`  | The whole suite against Postgres instead of SQLite, nightly |
| `composer test:mutate` | Mutation score over `app/`, weekly                          |
| `composer sbom`        | Write `sbom.json` (CycloneDX), attached to each release     |

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

Details per command sit with the thing it operates on:
[[operations/testing]], [[operations/tooling]], [[domains/console-commands]],
[[operations/documentation]].
