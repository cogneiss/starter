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
    - resources/js/pages/admin/announcement.tsx
    - app/Http/Controllers/Admin/AdminAnnouncementController.php
    - app/Http/Controllers/AnnouncementDismissalController.php
    - app/Models/Announcement.php
    - app/Actions/SaveAnnouncement.php
    - app/Actions/VisibleAnnouncements.php
    - app/Actions/DismissAnnouncement.php
    - resources/js/components/announcement-banners.tsx
    - tests/Feature/AnnouncementTest.php
    - tests/Feature/Controllers/AdminAccessTest.php
    - tests/Feature/Controllers/AdminPagesTest.php
updated: 2026-10-31
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

## Announcements

The `announcements` page is the one admin page with write screens:
`AdminAnnouncementController` creates, edits and deletes through
`SaveAnnouncement` and a Precognition form against `SaveAnnouncementRequest`.
The message field carries a draft-with-AI button (`announcement.body` in `DraftableFields`, gated by the `platform` ability) when the feature flag is on. An announcement has a level, a `starts_at`/`ends_at` window and an optional
organization (none means every organization). `VisibleAnnouncements` is
the single visibility query: `HandleInertiaRequests` shares the result for the
member's current organization, minus what `notDismissedBy()` filters out, and
`AnnouncementDismissalController` resolves its id through the same query, so a
foreign or expired id is a 404. Dismissals live in `announcement_dismissals`,
per user. The model is audited through `audit.extra`; a global announcement has
no organization, so the audit listener writes no entry for it (not
documented as a gap elsewhere).

## Health

`/admin` itself renders the health report — the same checks as `/health`
([[operations/ops-health]]). Every admin page view is written to the audit
log ([[operations/ops-audit-log]]): the operators are the most-watched users
in the system, not the least.
