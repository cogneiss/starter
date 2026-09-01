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
updated: 2026-09-01
---

# The audit log

An append-only record of who did what: model writes captured by
`RecordModelActivity` off the Eloquent wildcard events, plus named actions the
application records deliberately. Rows are never updated and never edited —
corrections are new rows.

## What a row holds

The actor, the organization, the event, the subject, and a redacted snapshot
of what changed. Redaction happens on the way in: secret-shaped attributes
(hashed tokens, webhook secrets, passwords) are stripped before the row is
written, so the log cannot leak what the models were careful not to. See
`tests/Feature/AuditRedactionTest.php` for the exact contract.

## Reading it

Organization members with the permission read their own organization's log
through the audit page — built on the list kit, so search, filters and CSV
export come for free and stay scoped. Super admins see across organizations
in the admin area ([[operations/ops-admin-area]]), and those very views are
themselves audited.

## Retention

`php artisan audit:prune` deletes entries older than
`AUDIT_RETENTION_DAYS` (365 by default, `config/audit.php`). GDPR deletion
anonymises the actor on old rows rather than destroying the trail —
[[operations/ops-gdpr]].
