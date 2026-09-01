---
title: Setting up a clone
status: current
supersedes: []
code_refs:
    - SETUP.md
    - .env.example
    - app/Console/Commands/DoctorCommand.php
updated: 2026-09-01
---

# Setting up a clone

`SETUP.md` is the step-by-step; this page is the shape of it and the parts people
get stuck on.

## One command

```bash
composer setup
```

That runs `composer install`, copies `.env.example` to `.env` if there is none,
generates the key, migrates, seeds the role templates, runs `bun install` and
builds the frontend. Then:

```bash
php artisan app:doctor
```

Doctor is the answer to "is it me or is it broken": PHP version, extensions,
coverage driver, `.env` and `APP_KEY`, database reachability, pending migrations,
`bun`, `node_modules`, the Vite manifest, stale generated TypeScript, and
writable `storage`/`bootstrap/cache`. It also runs the same six checks
`GET /health` answers with — database, cache, queue round-trip, schedule
heartbeat, disk headroom, debug mode — failing only on a hard failure, since a
degraded check still passes doctor. Every failure prints its fix
([[domains/console-commands]]).

Below the failures it prints an optional section — mail transport, the pgvector
extension, AI provider keys, the AI gateway, and the file scanner. Those are
reported, never failed, because the kit is designed to run with all of them
absent. The scanner entry reads unset when `UPLOAD_SCANNER` resolves to
`NullScanner`, which is the default: a machine with no ClamAV still imports
files, it just promotes them unscanned
([[domains/ux-import-and-uploads]]).

## What needs a service

Postgres, and only Postgres ([[operations/runtime]]). Sessions, queue, cache and
mail all sit on the database or the log, so there is no Redis or SMTP to install.

```bash
php artisan ai:install
```

creates the `vector` extension in the current database, which is what retrieval
stores embeddings in ([[domains/ai-retrieval]]). It is a no-op on any connection
that is not Postgres, so running it on the commented-out SQLite setup is safe and
pointless. Skipping it costs you vector search and nothing else.

AI provider keys are optional in the same way social keys are: with all three
blank the app boots, every page renders and the whole suite passes, because no
test is allowed to reach a provider ([[domains/ai-evals]]).

## Decisions a new project makes early

- **Resolver** — `ORGANIZATIONS_RESOLVER`, default `session`
  ([[domains/organization-resolvers]]).
- **Strict scoping** — leave `ORGANIZATIONS_STRICT=true` unless migrating an
  existing database ([[architecture/fail-closed-scoping]]).
- **Super admin** — `SETUP.md` has the steps; there is no UI for it on purpose.
- **Social login** — credentials in `.env` plus the feature flag; without both,
  the provider button is absent ([[domains/auth-drivers]]).
- **Live notifications** — `BROADCAST_CONNECTION` and the Reverb block, all
  blank by default; the bell reads the database until they are filled in
  ([[operations/runtime]], [[domains/ux-realtime-notifications]]).
- **Locales** — `config/app.php` lists what the language switcher offers, and a
  key present in one locale file and missing from another fails the suite
  ([[domains/ux-i18n]]).

## Git hooks

`composer install` points `core.hooksPath` at `.githooks`, which wires up the
commit-message check ([[operations/releases]]) and the graph rebuild
([[operations/code-knowledge-graphs]]). Nothing to run by hand.

The optional graph tools are `pip`/`npm` installs and degrade gracefully when
absent — the hook detects a missing binary and skips it.
