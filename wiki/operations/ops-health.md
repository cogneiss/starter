---
title: The health endpoint
status: current
supersedes: []
code_refs:
    - app/Http/Controllers/HealthController.php
    - app/Support/Health/HealthReport.php
    - app/Support/Health/Check.php
    - app/Support/Health/Checks/DatabaseCheck.php
    - app/Support/Health/Checks/QueueCheck.php
    - app/Support/Health/Checks/ScheduleCheck.php
    - bootstrap/app.php
    - tests/Feature/HealthTest.php
updated: 2026-09-01
---

# The health endpoint

`GET /health` (registered in `bootstrap/app.php`) answers with a JSON report
from `HealthReport`: one entry per check, an overall status, and a non-200
code when anything is failing — which is what an uptime monitor keys on.

## The checks

Each check is a small class implementing `Check` under
`app/Support/Health/Checks`: database connectivity, queue depth, cache
round-trip, disk, debug mode (on in production is a failure, not a warning),
and the scheduler. The scheduler check reads the `health-heartbeat` task's
last-run timestamp from `routes/console.php` — a scheduler that silently
stopped fails the endpoint honestly instead of everything looking green while
nothing runs.

## The admin panel

Super admins see the same report rendered at `/admin` through
`AdminHealthController` — same checks, same truth, no separate probe
([[operations/ops-admin-area]]). When a check fails in production it also
reaches the error reporter — [[operations/ops-error-reporting]].

Adding a check is one class and one line in the report; the endpoint and the
panel pick it up together.
