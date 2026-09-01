---
title: The admin control plane
status: current
supersedes: []
code_refs:
    - app/Admin/AdminResource.php
    - app/Admin/AdminResources.php
    - app/Http/Middleware/EnsurePlatformAdmin.php
    - app/Http/Controllers/Admin/AdminResourceController.php
    - app/Http/Controllers/Admin/AdminHealthController.php
    - app/Data/AdminRowData.php
    - resources/js/pages/admin/index.tsx
    - tests/Feature/Controllers/AdminAccessTest.php
    - tests/Feature/Controllers/AdminPagesTest.php
updated: 2026-09-01
---

# The admin control plane

A super-admin area at `/admin`, built on the same server-driven list kit as
every tenant screen ([[domains/ux-list-kit]]) rather than on a second admin
framework.

## The door

`EnsurePlatformAdmin` is the one gate: `Gate::allows('platform')`, granted
only to super admins, and a refusal is `abort(404)` — to anyone without the
ability the area does not exist, so the URL confirms nothing.

## The pages

`AdminResources` declares one `AdminResource` per page — organizations,
users, feature overrides, role templates, the impersonation log, the audit
log, API tokens and webhook endpoints. Each declares its columns, filters and
sort keys; `AdminResourceController` runs them through the list kit
cross-organization (`withoutOrganizationScope()`, deliberately, in exactly
this controller), so search, filters, sort and CSV export behave identically
to the tenant screens. The webhook endpoints page also carries the ten most
recent failed deliveries across all organizations
([[operations/ops-webhooks]]).

Token rows show names and metadata only — plaintext values were never stored,
so the admin area cannot leak them ([[operations/ops-api-tokens]]).

## Health

`/admin` itself renders the health report — the same checks as `/health`
([[operations/ops-health]]). Every admin page view is written to the audit
log ([[operations/ops-audit-log]]): the operators are the most-watched users
in the system, not the least.
