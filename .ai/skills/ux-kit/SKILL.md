---
name: ux-kit
description: "Use when building or changing a screen in this starter kit: a list with search, filters, sorting, saved views, column controls, bulk actions or CSV export; the ⌘K palette; a detail drawer; a form; an onboarding step; a bulk import or file upload; a notification; brand colours, motion or translated copy. Covers the server-driven list contract in ListsResources and ResourceQuery, query-level tenant scoping where a foreign id is a 404, the allMatching bulk predicate, URL state round-tripping, Precognition parity, scanned temporary uploads and the token modules for colour, motion and locale. The mistakes here are cross-organization leaks through a filtered export and controls that only exist in the browser, so read it before editing."
license: MIT
metadata:
    author: cogneiss
---

# The UX kit

Everything below is server-driven. The browser renders state it was given; it
never decides what a person is allowed to see.

## Building a list screen

Use `App\Http\Controllers\Concerns\ListsResources` on the controller and render
`data-table.tsx`. That gets you search, faceted filters, sorting, pagination,
column visibility, saved views, bulk selection, a detail drawer and CSV export
without writing any of them.

Three things are the trait's, not yours:

1. **The scope.** `App\Support\ResourceQuery` applies the organization scope
   before any filter. Never start from `Model::query()` in a controller that
   lists.
2. **The lookup.** `findListed()` resolves a single record through the same
   scoped query, so an id belonging to another organization is a 404 rather than
   a 403 about a record that exists. The `?peek=` drawer uses it too.
3. **The export.** CSV is the same query with the same filters and the same
   scope. Never rebuild it; a filtered export that starts from an unscoped query
   is a silent bulk leak, which is why `tests/Feature/CrossOrgLeakTest.php` has a
   case for it.

## Bulk actions

The request carries either explicit ids or `allMatching`. `allMatching` is a
predicate `App\Actions\ApplyBulkAction` re-runs against the scoped, filtered
query — never a list of ids the browser assembled — and it walks the result with
`lazyById()` so the size of the selection is not a number the request chooses.
Every record goes through the gate individually: checking the ability once and
then looping grants the whole selection on the strength of its easiest record.

## URL state

Filters, sort, page and visible columns serialize into the query string and
parse back out, so a link reproduces the screen. One pair of methods owns that
encoding: `ResourceQuery::fromRequest()` reads it and
`ResourceQuery::toQueryParameters()` writes it. Add a new list parameter to
both or the round trip stops being one.

## Forms

Reuse the form request. Precognition validates the same rules live on the way
in, and the parity gate fails when a precognitive route and its form request
disagree. Never restate a validation rule in TypeScript.

## Onboarding steps

Add a class under `app/Onboarding/Steps` with
`php artisan app:make-onboarding-step`. A step declares whether it is complete;
the checklist and the `onboarded` middleware read the registry, so registering
it is the whole wiring.

## Imports and uploads

`app/Imports` holds one class per import. An upload is written as a temporary
record, scanned, and only promoted to a real file after that. With no scanner
configured the kit uses `NullScanner` and promotes on trust — reported by
`app:doctor`, never silently assumed.

## Copy, colour, motion

- Strings live in `lang/<locale>/`, reach the browser through
  `resources/js/lib/i18n.ts`, and a key missing from any locale fails the suite.
- Colours come from the brand tokens, generated from the organization's two
  brand colours. No hex literals in components.
- Durations come from `resources/js/lib/motion.ts`, which returns zero when the
  operating system asks for reduced motion. No hard-coded `duration-200`.

## Before you call it done

```bash
herd php artisan test --compact --filter='CrossOrgLeak|UrlRoundTrip|BrandPalette'
bun run build
composer test:a11y
```

The reasoning behind each of these is in `wiki/domains/ux-list-kit.md`,
`wiki/domains/ux-filters-and-saved-searches.md`,
`wiki/domains/ux-forms-precognition.md`,
`wiki/domains/ux-import-and-uploads.md`, `wiki/domains/ux-i18n.md`,
`wiki/domains/ux-branding.md` and `wiki/domains/ux-motion-and-a11y.md`.
