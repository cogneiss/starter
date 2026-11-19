---
title: Hosting
status: current
supersedes: []
code_refs:
    - .env.example
    - config/database.php
    - config/queue.php
    - config/inertia.php
    - config/health.php
    - routes/console.php
    - bootstrap/app.php
    - composer.json
    - package.json
    - app/Console/Commands/DoctorCommand.php
    - app/Console/Commands/AiInstallCommand.php
    - database/migrations/2026_08_25_090000_create_ai_documents_table.php
    - app/Support/Health/HealthReport.php
    - .work/reports/Laravel Inertia starter rebuild 2026.md
updated: 2026-11-20
---

# Hosting
The kit ships no hosting recipe. There is no Dockerfile, no compose file and no
host config in the repo. This page lists what the app needs from any host, then
what each target has to supply. Host facts come only from the research report.
Where it is silent, check with the host.

## What the kit needs

**Web.** A PHP 8.5 process serving the app. Change `APP_URL` from the
`http://localhost:8000` that `.env.example` sets.

**Queue worker.** `QUEUE_CONNECTION` defaults to `database` (`config/queue.php`,
`.env.example`). A worker must run, or queued jobs and the health queue check
both stall. `app:doctor` tells you to start one with `php artisan queue:work`
(`app/Console/Commands/DoctorCommand.php`).

**Scheduler.** `routes/console.php` registers a heartbeat every minute and the
daily prune and purge tasks, plus an hourly sweep that closes impersonation
logs whose session expired. Something must run the scheduler every minute.
`app:doctor` names `php artisan schedule:work` for the long-running form.

**Reverb.** Optional. `BROADCAST_CONNECTION` is `null` in `.env.example`, and
notifications still work through the database. To turn live updates on, set it
to `reverb`, fill the blank `REVERB_APP_*` values and their `VITE_REVERB_*`
mirrors, and run a Reverb server. `.env.example` presets host `localhost`, port
`8080`, scheme `http`. `laravel/reverb` is a dependency in `composer.json`. The
server command and any proxy setup come from that package, and this repo does
not document them. The browser must be able to reach the Reverb port.

**Inertia SSR.** Optional and off. `config/inertia.php` reads
`INERTIA_SSR_ENABLED` (false by default) and calls `http://127.0.0.1:13714`
unless `INERTIA_SSR_URL` says otherwise. To use it, build with
`bun run build:ssr` (`package.json`) and keep `php artisan inertia:start-ssr`
running. It also needs the configured runtime, `node` by default, on the host.

**Assets.** `bun run build` produces the client bundle (`package.json`).

**Postgres and pgvector.** `.env.example` defaults to `DB_CONNECTION=pgsql`
and `config/database.php` carries the connection. That file also pins the
session time zone to UTC on PostgreSQL, MySQL and MariaDB, so the server's
default zone does not matter to the application. A `psql` shell opened by
hand still uses the server default. Retrieval stores embeddings
in a real vector column. The migration
`database/migrations/2026_08_25_090000_create_ai_documents_table.php` calls
`Schema::ensureVectorExtensionExists()` on Postgres, and
`php artisan ai:install` does the same (`AiInstallCommand.php`). So the database
role must be allowed to create the extension, or the host must have it enabled
already. On any other driver the column is plain text and there is no vector
search ([[domains/ai-retrieval]]).

**Health.** `GET /health` returns a JSON report and a non-200 code when a check
fails. It is registered in `bootstrap/app.php` with no web middleware, so it
answers while the database is down. It checks database, cache, disk, queue and
schedule, and warns when `APP_DEBUG` is on in production, so a green report also proves the worker and scheduler run
(`app/Support/Health/HealthReport.php`, `config/health.php`). Point the host's
health probe or an uptime monitor at it ([[operations/ops-health]]).

## Laravel Cloud
The report says Laravel Cloud Serverless Postgres supports pgvector, so Cloud
is a valid target (report, "Hosting topology" row, dated 2026-11-17). Nothing
else about Cloud is in the report.

- Running: the report does not say how Cloud runs a worker, the scheduler,
  Reverb or SSR. Check each against the needs above first.
- Postgres: pgvector is supported per the report. Confirm the migration can
  enable the extension with the role Cloud gives you.
- Health: use `/health`. Not covered: setup steps, limits, pricing.

## Laravel Forge on a plain VPS
The report does not mention Forge. This follows from the needs above.

- Running: you own the processes. Run `queue:work`, the scheduler, Reverb if
  enabled and `inertia:start-ssr` if enabled, each under a supervisor so it
  restarts. Run the scheduler every minute.
- Postgres: install pgvector on the server yourself, then run the migration or
  `php artisan ai:install` with a role that may create extensions.
- PHP: the report asks you to confirm a current PHP 8.5 patch release and set
  `session.upload_progress.enabled = Off`. `app:doctor` reports both: the
  patch level against a floor hard-coded in `DoctorCommand.php`, and the ini
  value.
- Health: use `/health`. Not covered: Forge menus, server sizing.

## Docker
No image or compose file exists in this repo. The report says only to confirm
that Docker images run a current PHP 8.5 patch release. If you write one, it
needs these pieces:

- One image for web, with PHP 8.5, the Postgres PDO driver and a built asset
  bundle.
- The same image run as separate containers for the queue worker, the
  scheduler and, if enabled, Reverb and SSR. SSR also needs the runtime
  (`node` by default).
- A Postgres image that ships pgvector, or a managed Postgres that has it.
- Ports: the web port, plus `8080` for Reverb and `13714` for SSR when
  enabled. Keep SSR internal.
- Health: a container health check against `/health`. Not covered: base
  image choice, build stages, volumes, secrets.

## Not covered anywhere
TLS, backups, scaling, zero-downtime deploys and the deploy command order.
`composer.json` has a `setup` script for a local checkout only.
