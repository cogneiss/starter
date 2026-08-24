---
title: Setting up a clone
status: current
supersedes: []
code_refs:
    - SETUP.md
    - .env.example
    - app/Console/Commands/DoctorCommand.php
updated: 2026-08-24
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
writable `storage`/`bootstrap/cache`. Every failure prints its fix
([[domains/console-commands]]).

## What needs no service

Nothing. SQLite, database sessions, queue and cache, and the log mailer mean a
fresh clone runs with no Postgres, Redis or SMTP anywhere
([[operations/runtime]]).

## Decisions a new project makes early

- **Resolver** — `ORGANIZATIONS_RESOLVER`, default `session`
  ([[domains/organization-resolvers]]).
- **Strict scoping** — leave `ORGANIZATIONS_STRICT=true` unless migrating an
  existing database ([[architecture/fail-closed-scoping]]).
- **Super admin** — `SETUP.md` has the steps; there is no UI for it on purpose.
- **Social login** — credentials in `.env` plus the feature flag; without both,
  the provider button is absent ([[domains/auth-drivers]]).

## Git hooks

`composer install` points `core.hooksPath` at `.githooks`, which wires up the
commit-message check ([[operations/releases]]) and the graph rebuild
([[operations/code-knowledge-graphs]]). Nothing to run by hand.

The optional graph tools are `pip`/`npm` installs and degrade gracefully when
absent — the hook detects a missing binary and skips it.
