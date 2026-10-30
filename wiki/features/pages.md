---
title: Pages
status: current
supersedes: []
code_refs:
    - routes/web.php
    - resources/js/pages/welcome.tsx
    - resources/js/pages/dashboard.tsx
    - resources/js/components/activity-chart.tsx
    - resources/js/pages/value-gallery.tsx
    - resources/js/pages/organization/ai-usage.tsx
updated: 2026-10-31
---

# Pages

What ships rendered, and nothing more:

- `/` — the public welcome page (`resources/js/pages/welcome.tsx`), named route
  `home`.
- `/dashboard` — behind `auth`, `verified`, `organization`, `two-factor` and `onboarded`
  (`resources/js/pages/dashboard.tsx`, which declares its `AppLayout` and
  breadcrumbs through `Dashboard.layout` rather than wrapping its markup). It renders server-supplied count widgets,
  a deferred API-requests-per-day chart (`resources/js/components/activity-chart.tsx`,
  a skeleton until the data arrives; the page reads the `activity` prop through `Deferred`, drawn with recharts) and, until it is finished or dismissed, the activation checklist
  ([[domains/ux-onboarding]]).
- `/csrf-token` and `/.well-known/passkey-endpoints` have no page: the first is an
  empty response, the second a JSON pointer to the passkey settings screen.
- The auth and settings screens listed in [[features/authentication]] and
  [[features/account-settings]].
- The organization screens in [[features/organizations]], including
  `/settings/organization/ai-usage`
  (`resources/js/pages/organization/ai-usage.tsx`), which renders AI spend
  through the same block components an agent answers with
  ([[domains/ai-blocks]]) rather than a hand-built table.
- `/onboarding` — the activation checklist a new owner meets before the rest of
  the application (`resources/js/pages/onboarding/show.tsx`,
  [[domains/ux-onboarding]]).
- `/settings/imports/*` — pick an import, download its template, upload a file,
  watch the batch, retry the failed rows (`resources/js/pages/import`,
  [[domains/ux-import-and-uploads]]).
- `/settings/notifications` — which notifications reach which channel
  (`resources/js/pages/user-notification-preference/edit.tsx`,
  [[domains/ux-realtime-notifications]]).
- `/_value-gallery` and `/_block-gallery` — every semantic value component and
  every AI block rendered with content and without, outside production only
  (`resources/js/pages/value-gallery.tsx`,
  `resources/js/pages/block-gallery.tsx`, see [[features/interface]] and
  [[domains/ai-blocks]]).
- `resources/js/pages/error.tsx` is not routed. Inertia renders it for a thrown
  status, so a 403, 404 or 500 is a page in the application's own shell rather
  than a framework default ([[domains/ux-primitives]]).
- Announcement banners render on every in-app page through the app shell, from
  the shared `announcements` prop; `/admin/announcements/create` and
  `/admin/announcements/{announcement}/edit` are the admin form
  ([[operations/ops-admin-area]]).
- `/admin` — a super-admin control plane on the same list kit as the tenant
  screens, gated so a refusal is a 404 rather than a 403
  ([[operations/ops-admin-area]]).

Everything else is yours to build. A starter kit that ships a settings page for a
product it cannot know about leaves you deleting code before writing any.

Page components live in `resources/js/pages`, one directory per resource, and
routing stays server-side through `Inertia::render` in `routes/web.php` and the
controllers ([[domains/http-layer]]).
