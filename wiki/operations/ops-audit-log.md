---
title: The audit log
status: current
supersedes: []
code_refs:
    - app/Listeners/RecordModelActivity.php
    - app/Models/Activity.php
    - app/Http/Controllers/AuditLogController.php
    - app/Console/Commands/PruneAuditLogCommand.php
    - config/audit.php
    - tests/Feature/AuditLogTest.php
    - tests/Feature/AuditRedactionTest.php
    - app/Resources/Definitions/AuditLogResource.php
updated: 2026-11-18
---

# The audit log

An append-only record of who did what: model writes captured by
`RecordModelActivity` off the Eloquent wildcard events, plus named actions the
application records deliberately. Rows are never updated and never edited —
corrections are new rows.

`ImpersonationLog` and `LoginHistory` keep their own tables and each bridges one
entry into this log on create; `ImpersonationLog` bridges one more when it ends
(`ended_at` changes). An impersonation entry takes the log's own organization,
and a target with no organization gets a platform-level entry with a null one.

## What a row holds

The actor, the organization, the event, the subject, and a redacted snapshot
of what changed. Redaction happens on the way in: secret-shaped attributes
(hashed tokens, webhook secrets, passwords) are stripped before the row is
written, so the log cannot leak what the models were careful not to. See
`tests/Feature/AuditRedactionTest.php` for the exact contract. During an
impersonated session the row's `properties` also carry `impersonator_id`; the
causer stays the person being impersonated.

## Reading it

Organization members with the permission read their own organization's log
through the audit page — built on the list kit, so search, filters and CSV
export come for free and stay scoped. The page is `AuditLogController`, which authorizes `viewAny` on `Activity` and
serves the list and the CSV export through the list kit. The kit's definition is
`AuditLogResource` (key `audit-log`, `app/Resources/Definitions/AuditLogResource.php`):
search on `description`; sort on `created_at`, `event` and `description`; filters
for member, event (created, updated, deleted, role_changed, exported), record type
(from `audit.models` and `audit.extra`) and a date range. Member options are the
causer ids already present in the log. Super admins see across organizations
in the admin area ([[operations/ops-admin-area]]), and those very views are
themselves audited.

`config/audit.php` has two lists: `models` for those using
`BelongsToOrganization`, and `extra` for audited models that do not.
`Announcement` is in `extra`: a null organization means a global banner, so the
model is deliberately unscoped, yet writing one is still an auditable act.

## Retention

`php artisan audit:prune` deletes entries older than
`AUDIT_RETENTION_DAYS` (365 by default, and also when the value is blank or not a positive number,
`config/audit.php`). GDPR deletion
anonymises the actor on old rows rather than destroying the trail —
[[operations/ops-gdpr]].
