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
    - app/Console/Commands/WikiAuditCommand.php
    - tests/Feature/ZeroKeyBootTest.php
    - app/Support/WikiPage.php
    - routes/console.php
updated: 2026-08-24
---

# Console commands

Eight first-party commands. Each one either answers a question a newcomer would
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
| `php artisan wiki:audit`                   | `WikiAuditCommand`              | write the `/document` worklist, never blocks |

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

Third-party credentials are listed separately, under `optional` in the JSON and
as `off — …` lines in the table: social login, the mail transport, the s3 disk
and the Slack token. None of them move the exit code. A blank
`GOOGLE_CLIENT_ID` means social login is off, not that the machine is broken,
and printing it as FAIL next to a missing `APP_KEY` is how people learn to skim
past this command. `tests/Feature/ZeroKeyBootTest.php` is the other half of that
claim: it blanks every credential through the config layer and asserts each page
the kit ships still answers 200.

## wiki:lint

Five rules over `wiki/**`, all blocking, backed by `app/Support/WikiPage.php` for
parsing. `wiki/_meta/lint.md` documents the rules; the loop around them is
[[operations/documentation]].

Every command is listed in `FEATURES.md` — see [[operations/commands]].
