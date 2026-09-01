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
    - app/Console/Commands/AiInstallCommand.php
    - app/Console/Commands/AiUsageCommand.php
    - tests/Feature/ZeroKeyBootTest.php
    - app/Support/WikiPage.php
    - routes/console.php
updated: 2026-09-01
---

# Console commands

Eighteen first-party commands. Each one either answers a question a newcomer
would otherwise ask a human, or maintains something that would otherwise drift.

| Command                                    | Class                           | Does                                           |
| ------------------------------------------ | ------------------------------- | ---------------------------------------------- |
| `php artisan app:doctor`                   | `DoctorCommand`                 | can this machine run, test and build the app   |
| `php artisan app:make-resource <Name>`     | `MakeResourceCommand`           | scaffold a resource and everything around it   |
| `php artisan app:sync-permissions`         | `SyncPermissionsCommand`        | write the permission catalog to the database   |
| `php artisan app:expire-feature-overrides` | `ExpireFeatureOverridesCommand` | drop feature overrides past their expiry       |
| `php artisan resource:cache`               | `ResourceCacheCommand`          | cache the resource registry for production     |
| `php artisan resource:clear`               | `ResourceClearCommand`          | undo the cache                                 |
| `php artisan wiki:lint`                    | `WikiLintCommand`               | fail the build when a wiki page has rotted     |
| `php artisan wiki:audit`                   | `WikiAuditCommand`              | write the `/document` worklist, never blocks   |
| `php artisan ai:install`                   | `AiInstallCommand`              | prepare the database for the AI layer          |
| `php artisan ai:usage`                     | `AiUsageCommand`                | report AI runs, tokens and spend               |
| `php artisan app:make-onboarding-step`     | `MakeOnboardingStepCommand`     | scaffold an onboarding step and its test       |
| `php artisan brand:preview <hex> <hex>`    | `BrandPreviewCommand`           | derive a palette and check every contrast      |
| `php artisan uploads:prune`                | `PruneUploadsCommand`           | delete temp uploads past their retention       |
| `php artisan tokens:prune`                 | `PruneApiTokensCommand`         | delete revoked or expired API tokens           |
| `php artisan api:prune-logs`               | `PruneApiRequestLogsCommand`    | delete API usage log rows past retention       |
| `php artisan audit:prune`                  | `PruneAuditLogCommand`          | delete audit log entries past retention        |
| `php artisan gdpr:export <user>`           | `GdprExportCommand`             | queue a personal-data export for a user        |
| `php artisan gdpr:purge`                   | `GdprPurgeCommand`              | hard-delete anonymised accounts past retention |

`routes/console.php` holds the schedule for the ones that run unattended, all
daily: feature-override expiry, `LoginHistory` pruning, `uploads:prune`,
`tokens:prune`, `api:prune-logs`, `audit:prune` and `gdpr:purge`. An unpromoted
upload is a file nobody claimed, so leaving it on disk is a slow leak rather
than a kept record ([[domains/ux-import-and-uploads]]). The same file also
schedules a `health-heartbeat` job every minute, writing the timestamp
`GET /health`'s schedule check reads to detect a stopped scheduler
([[operations/ops-health]]).

`brand:preview` is the one that exists to be run before a design decision rather
than after a deploy: it prints the derived palette and the contrast ratio of
every pair, so a brand colour that fails AA is caught in a terminal instead of an
audit ([[domains/ux-branding]]).

## app:doctor

The onboarding command. It checks the PHP version against `composer.json`,
required extensions, a coverage driver, `.env` and `APP_KEY`, database
reachability, pending migrations, `bun`, `node_modules`, the Vite manifest, stale
generated TypeScript, and writable `storage`/`bootstrap/cache`. It also runs the
same six checks `GET /health` runs — database, cache, queue, schedule heartbeat,
disk headroom and debug mode — one line each; a degraded check still passes the
doctor, only a hard failure does not ([[operations/ops-health]]). Every failure
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
the kit ships still answers 200. It skips `csrf-token`, which is reachable by GET
but reissues the session cookie and answers 204 — the right answer for it, and
the wrong shape for a page assertion. It only sweeps routes carrying the `web`
middleware, so the token-authenticated `/api/v1` routes do not switch the
default guard mid-sweep, and it checks `/admin` separately, with a super admin
signed in, rather than folding it into the same 200 assertion
([[operations/ops-admin-area]]).

The file scanner joins the optional block for the same reason: a machine with no
scanner still imports files, it just imports them on trust, and
`UPLOAD_SCANNER` naming nothing known degrades to `NullScanner` rather than to a
broken binding ([[domains/ux-import-and-uploads]]).

The AI credentials sit in the same optional block, alongside two reports that
never move the exit code either: whether quotas are configured, and whether every
model the application names has a price. A missing price is not a broken machine,
it is a bill reported as zero, which is worth reading before it surprises someone
([[domains/ai-metering-and-quotas]]).

## ai:install and ai:usage

`ai:install` creates the pgvector extension so retrieval has somewhere to search.
On any connection other than PostgreSQL it is a deliberate no-op rather than an
error — the rest of the layer works on SQLite, which is what `composer
test:sqlite` proves ([[domains/ai-retrieval]]).

`ai:usage` reads the audit log and prints runs, tokens and spend for the last 30
days, split by agent and by tier. `--org` limits it to one organization by id or
slug, `--since` takes anything `strtotime` understands, and `--json` emits the
same figures for a script. It shares `app/Actions/SummarizeAiUsage.php` with the
organization page, so the console and the browser cannot report different
numbers.

## wiki:lint

Five rules over `wiki/**`, all blocking, backed by `app/Support/WikiPage.php` for
parsing. `wiki/_meta/lint.md` documents the rules; the loop around them is
[[operations/documentation]].

Every command is listed in `FEATURES.md` — see [[operations/commands]].
