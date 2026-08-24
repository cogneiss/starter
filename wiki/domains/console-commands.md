---
title: Console commands
status: current
supersedes: []
code_refs:
    - app/Console/Commands/DoctorCommand.php
    - app/Console/Commands/MakeResourceCommand.php
    - app/Console/Commands/SyncPermissionsCommand.php
    - app/Console/Commands/ExpireFeatureOverridesCommand.php
    - app/Console/Commands/ResourceCacheCommand.php
    - app/Console/Commands/ResourceClearCommand.php
    - app/Console/Commands/WikiLintCommand.php
    - app/Support/WikiPage.php
    - routes/console.php
updated: 2026-08-24
---

# Console commands

Seven first-party commands. Each one either answers a question a newcomer would
otherwise ask a human, or maintains something that would otherwise drift.

| Command                                    | Class                           | Does                                         |
| ------------------------------------------ | ------------------------------- | -------------------------------------------- |
| `php artisan app:doctor`                   | `DoctorCommand`                 | can this machine run, test and build the app |
| `php artisan app:make-resource <Name>`     | `MakeResourceCommand`           | scaffold a resource and everything around it |
| `php artisan app:sync-permissions`         | `SyncPermissionsCommand`        | write the permission catalog to the database |
| `php artisan app:expire-feature-overrides` | `ExpireFeatureOverridesCommand` | drop feature overrides past their expiry     |
| `php artisan resource:cache`               | `ResourceCacheCommand`          | cache the resource registry for production   |
| `php artisan resource:clear`               | `ResourceClearCommand`          | undo the cache                               |
| `php artisan wiki:lint`                    | `WikiLintCommand`               | fail the build when a wiki page has rotted   |

`routes/console.php` holds the schedule for the ones that run unattended.

## app:doctor

The onboarding command. It checks the PHP version against `composer.json`,
required extensions, a coverage driver, `.env` and `APP_KEY`, database
reachability, pending migrations, `bun`, `node_modules`, the Vite manifest, stale
generated TypeScript, and writable `storage`/`bootstrap/cache`. Every failure
prints the command that fixes it, `--json` is there for scripts, and the exit
code is 1 if anything failed.

It exists because the alternative is a README section that goes stale and a
newcomer who cannot tell a missing extension from a broken checkout.

## wiki:lint

Five rules over `wiki/**`, all blocking, backed by `app/Support/WikiPage.php` for
parsing. `wiki/_meta/lint.md` documents the rules; the loop around them is
[[operations/documentation]].

Every command is listed in `FEATURES.md` — see [[operations/commands]].
