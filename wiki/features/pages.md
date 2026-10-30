---
title: Pages
status: current
supersedes: []
code_refs:
    - routes/web.php
    - app/Http/Controllers/BlogController.php
    - app/Http/Controllers/ChangelogController.php
    - app/Http/Controllers/SitemapController.php
    - app/Actions/LoadBlogPosts.php
    - app/Actions/RenderMarkdown.php
    - resources/js/components/seo.tsx
    - config/site.php
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
- `/blog`, `/blog/{slug}` and `/changelog` — the public site beside the welcome
  page. A post is a Markdown file in `content/blog/` with `title`, `description`
  and `date` front matter (`author` and `draft` optional), read by
  `App\Actions\LoadBlogPosts`; drafts show everywhere except production. The
  slug is matched against the loaded posts and constrained to `[a-z0-9-]+`, so
  it never becomes a path. `/changelog` renders the repository's `CHANGELOG.md`.
  Both go through `App\Actions\RenderMarkdown`, which escapes raw HTML and drops
  unsafe links. There is no admin screen and no pricing page; both are left out
  on purpose.
- `/sitemap.xml` lists the home page, blog index, every visible post and the
  changelog, and nothing behind a sign-in. `/robots.txt` is a route (not a static
  file) so its `Sitemap:` line carries the application's absolute URL.
- The `Seo` component (`resources/js/components/seo.tsx`) sets description,
  canonical URL, Open Graph and Twitter tags through Inertia's `Head` on the
  welcome, blog and changelog pages. The canonical URL is the shared `appUrl`
  plus the path without its query string. The tags reach the first HTML response
  only while the SSR server (`resources/js/ssr.tsx`, built by `bun run
  build:ssr`) is running; otherwise the client adds them on render.
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
