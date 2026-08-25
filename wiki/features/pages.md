---
title: Pages
status: current
supersedes: []
code_refs:
    - routes/web.php
    - resources/js/pages/welcome.tsx
    - resources/js/pages/dashboard.tsx
    - resources/js/pages/value-gallery.tsx
    - resources/js/pages/organization/ai-usage.tsx
updated: 2026-08-25
---

# Pages

What ships rendered, and nothing more:

- `/` — the public welcome page (`resources/js/pages/welcome.tsx`), named route
  `home`.
- `/dashboard` — behind `auth`, `verified`, `organization` and `two-factor`
  (`resources/js/pages/dashboard.tsx`).
- The auth and settings screens listed in [[features/authentication]] and
  [[features/account-settings]].
- The organization screens in [[features/organizations]], including
  `/settings/organization/ai-usage`
  (`resources/js/pages/organization/ai-usage.tsx`), which renders AI spend
  through the same block components an agent answers with
  ([[domains/ai-blocks]]) rather than a hand-built table.
- `/_value-gallery` — every semantic value component rendered with a value and
  without, outside production only
  (`resources/js/pages/value-gallery.tsx`, see [[features/interface]]).

Everything else is yours to build. A starter kit that ships a settings page for a
product it cannot know about leaves you deleting code before writing any.

Page components live in `resources/js/pages`, one directory per resource, and
routing stays server-side through `Inertia::render` in `routes/web.php` and the
controllers ([[domains/http-layer]]).
