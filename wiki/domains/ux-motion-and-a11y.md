---
title: Motion and accessibility
status: current
supersedes: []
code_refs:
    - resources/js/lib/motion.ts
    - resources/js/components/route-announcer.tsx
    - resources/css/app.css
    - tests/Unit/Support/ReducedMotionTest.php
    - tests/Unit/Support/MotionTest.php
    - tests/Unit/Conventions/AriaLiveGuardTest.php
    - tests/Browser/AccessibilityTest.php
    - tests/Browser/RouteAnnouncerTest.php
updated: 2026-08-31
---

# Motion and accessibility

## Named transitions, not per-component animation

`resources/js/lib/motion.ts` holds every transition this application animates
with. Two rules are baked into the module rather than left to each component.

**Only `transform` and `opacity` move.** Both are composited, so they cost no
layout and no paint. Animating a box's size or position makes the browser
re-lay-out the page on every frame, which is where dropped frames on a long list
come from.

**Reduced motion is answered by removing the animation, not shortening it.** A
quicker slide is still a slide, and the setting is a request for no movement at
all — so every named transition emits a zero duration when it is on
(`tests/Unit/Support/ReducedMotionTest.php`). `resources/css/app.css` makes the
same promise for CSS-driven animation under
`@media (prefers-reduced-motion: reduce)`.

## Announcing single-page navigations

A full page load makes the browser announce the new document. An Inertia visit
does not: the document stays put and its contents change, so without help a
screen reader reports nothing and the reader is left on a page that silently
became a different one.

`<RouteAnnouncer>` reads `document.title` one frame after the visit — that is
what `<Head title>` has just written, and it is the same sentence a full load
would have announced — and puts it in a polite live region.

## One interrupting region

`aria-live="assertive"` interrupts whatever a screen reader is saying. Two
regions competing for that is a reader who hears half of each. The convention
test `tests/Unit/Conventions/AriaLiveGuardTest.php` walks the component tree and
lets only the allowlisted error region interrupt; everything else — the
announcer included — is polite. The same test keeps the allowlist honest, so the
exception cannot quietly grow.

## The blocking accessibility run

`composer test:a11y` drives the real browser over the signed-out pages, the
signed-in pages, the password-confirmation gate, the two-factor challenge, the
palette with results open, and the detail drawer with the announcer alongside it
(`tests/Browser/AccessibilityTest.php`). It is a gate, not a report.

## Related

- [[domains/ux-primitives]] — the drawer and dialog whose focus behaviour this covers
- [[domains/ux-branding]] — the contrast half of accessibility
