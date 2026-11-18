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
    - app/Data/AnnouncementData.php
    - app/Enums/AnnouncementLevel.php
    - app/Http/Requests/SaveAnnouncementRequest.php
    - app/Http/Controllers/Admin/AdminAiCreditController.php
    - app/Http/Requests/GrantAiCreditRequest.php
    - resources/js/pages/admin/ai-credit.tsx
    - tests/Feature/Controllers/AdminAiCreditTest.php
updated: 2026-11-18
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
`SaveAnnouncement` and a Precognition form against `SaveAnnouncementRequest`,
which the page builds with the `useForm` that `@inertiajs/react` ships.
The message field carries a draft-with-AI button (`announcement.body` in `DraftableFields`, gated by the `platform` ability) when the feature flag is on. `SaveAnnouncementRequest` requires a title (255), a body (500), a level, a `starts_at`, an optional `ends_at` after it, and an optional existing organization id. `AnnouncementLevel` is `info` or `warning`. `AnnouncementData` (shared to the front end as `AnnouncementBanner`) is the id, title, body and level the banner needs. An announcement has a level, a `starts_at`/`ends_at` window and an optional
organization (none means every organization). `VisibleAnnouncements` is
the single visibility query: `HandleInertiaRequests` shares the result for the
member's current organization, minus what `notDismissedBy()` filters out, and
`AnnouncementDismissalController` resolves its id through the same query, so a
foreign or expired id is a 404. Dismissals live in `announcement_dismissals`,
per user. The model is audited through `audit.extra`; a global announcement has
no organization, so the audit listener writes no entry for it (not
documented as a gap elsewhere).

## Granting AI credit

The organizations page links to a "Grant AI credit" form. Its organization
picker holds at most 20 `OrganizationData` rows, sorted by name, and a search
box narrows them on the server with a name match, so the page stays the same
size however many organizations exist. A new search clears the choice, so a
grant never goes to an organization the picker no longer shows.
`AdminAiCreditController` posts through `GrantAiCredits` with no actor, because
a platform admin is not a member holding `ai.grant`; the platform gate in front
of the route is the authorization, and a non-admin gets a 404.
`GrantAiCreditRequest` requires an existing organization, a whole number of
micros from 1 to 1,000,000,000 (a thousand dollars) and a reason of up to 255
characters, and validates live through Precognition. Each grant also writes an
`admin` / `granted` audit entry filed under the target organization, naming the
admin as causer with the ledger row as its subject
([[operations/ops-audit-log]], [[domains/ai-metering-and-quotas]]).

## Health

`/admin` itself renders the health report — the same checks as `/health`
([[operations/ops-health]]). Every admin page view is written to the audit
log ([[operations/ops-audit-log]]): the operators are the most-watched users
in the system, not the least.
