---
title: The health endpoint
status: current
supersedes: []
code_refs:
    - app/Http/Controllers/HealthController.php
    - app/Support/Health/HealthReport.php
    - app/Support/Health/Checks/DatabaseCheck.php
    - app/Support/Health/Checks/DebugModeCheck.php
    - app/Support/Health/Checks/DiskCheck.php
    - app/Support/Health/Checks/QueueCheck.php
    - app/Support/Health/Checks/ScheduleCheck.php
    - app/Providers/AppServiceProvider.php
    - config/health.php
    - bootstrap/app.php
    - tests/Feature/HealthTest.php
    - app/Enums/HealthStatus.php
    - app/Jobs/QueueHeartbeat.php
updated: 2026-11-18
---

# The health endpoint

`GET /health` (registered in `bootstrap/app.php`) answers with a JSON report
from `HealthReport`: one entry per check, an overall status, and a non-200
code when anything is failing — which is what an uptime monitor keys on. The route is added in the
`then` callback of `withRouting()` with no middleware group, on purpose: the web
group's session store is the database, and the endpoint has to answer while the
database is down. The same call passes `health: '/up'`, which registers the
framework's own built-in health route; that route is separate from this report
and its response is not defined in this repository.

## The checks

The checks run on `spatie/laravel-health`. `AppServiceProvider::boot()`
registers them on the package's `Health` registry, and `HealthReport` runs each
one, times it and folds the results into the overall status. `HealthStatus` is the three-value outcome: `ok`, `degraded` (the app can serve,
somebody should look) and `failed`. The package's warning maps to `degraded`,
and `HealthController` answers 503 only when the overall status is `failed`.
A check that
throws, or returns anything but ok or warning, counts as failed, and the
exception message is dropped because the endpoint is public.

The cache round-trip is the package's own check, named `cache`. The other five
are kit classes under `app/Support/Health/Checks` that extend the package's
`Check`, each because the package's version answers a different question. Each
class docblock says why. `DatabaseCheck` runs `select 1` where the package's
check only opens a connection, because behind a pooler or in a long-lived
worker an open connection proves nothing. Its failure message is fixed text,
never the driver error.
`QueueCheck` dispatches a real heartbeat job and waits for a worker to mark it
processed. The job is `QueueHeartbeat`: it writes the one-off token it was
given into the cache for 60 seconds, which is the worker's proof of life. `DiskCheck` warns on a path it cannot measure instead of reading it
as empty. `DebugModeCheck` warns, rather than fails, when `APP_DEBUG` is on in
production, because the package's check would fail every local machine.
`ScheduleCheck` reads the cache key the `health-heartbeat` task in
`routes/console.php` stamps every minute, so a scheduler that silently stopped
shows up instead of everything looking green while nothing runs. A stale
heartbeat is a warning, so a fresh clone still answers 200. Thresholds live in
`config/health.php`.

Results are held in memory only. `config/health.php` sets the in-memory result
store and turns package notifications off, so there is no health table.
`tests/Feature/HealthTest.php` asserts both.

## The admin panel

Super admins see the same report rendered at `/admin` through
`AdminHealthController` — same checks, same truth, no separate probe
([[operations/ops-admin-area]]).

Adding a check is one class extending the package's `Check` and one line in the
`Health::checks()` list in `AppServiceProvider`; the endpoint and the panel pick
it up together.
