---
title: Interface
status: current
supersedes: []
code_refs:
    - resources/js/app.tsx
    - resources/js/ssr.tsx
    - resources/js/components/value/money.tsx
    - resources/js/components/value/empty-value.tsx
    - resources/js/components/value/relative-time.tsx
    - components.json
    - vite.config.ts
updated: 2026-08-24
---

# Interface

Inertia v3 and React 19, server-side routing, Tailwind v4. `resources/js/app.tsx`
is the entry point; `resources/js/ssr.tsx` is the SSR one, built with
`bun run build:ssr`.

## Layouts and components

- Two app shells (sidebar or header nav) and three auth layouts (simple, card,
  split) in `resources/js/layouts` — swap by changing one import.
- A shadcn-style component set on Base UI primitives (`base-nova`), so keyboard
  navigation, focus management and ARIA come from the primitive rather than from
  each component remembering.
- `components.json` is configured for the shadcn CLI (new-york style), so
  `bunx shadcn@latest add ...` drops components into
  `resources/js/components/ui`.
- Responsive down to mobile, collapsible sidebar with persisted state.
- Breadcrumbs, flash toasts (Sonner, flashed from the server with
  `Inertia::flash('toast', ...)`), loading spinners on submit, inline field
  errors, and a show/hide toggle on password fields with a real `aria-label`.
- Small hooks in `resources/js/hooks` for the fiddly parts: clipboard copy for
  recovery codes, initials for avatars, mobile breakpoint and navigation, current
  URL, appearance.

## Semantic value components

`resources/js/components/value` holds the thirteen values every app renders the
same way and formats differently on every page: `Money`, `Percent`, `DateValue`,
`RelativeTime`, `BooleanPill`, `StatusBadge`, `EmailValue`, `UrlValue`,
`PhoneValue`, `TagList`, `CodeValue`, `LongText`, and the `EmptyValue` the rest
fall back to.

Two properties are the reason they exist:

1. Hand any of them `null` and you get one em-dash with a screen-reader label.
   Never `NaN`, never `null`, never `Invalid Date`. That is what
   `empty-value.tsx` is for, and why the others delegate to it rather than each
   inventing a fallback.
2. Formatting goes through the platform's `Intl` APIs, so currency, number and
   date rendering follow the locale instead of a hand-rolled helper that is
   correct in one region.

`/_value-gallery` renders all of them with a value and without, outside
production. They render; they do not animate — the motion layer was cut
deliberately ([[decisions/resource-spine]]).

## Build

Vite+ (Rolldown) via `vite.config.ts`, with the React Compiler enabled through
Babel, so manual `useMemo` and `useCallback` are not needed. Preload `Link`
headers are added to responses, and the `appearance` and `sidebar_state` cookies
are left unencrypted so the server can read them before hydration
([[features/account-settings]]).

Accessibility is gated, not assumed: every page ships through axe-core at level 3
([[operations/testing]]).
