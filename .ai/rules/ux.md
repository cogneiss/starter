---
paths:
    - "app/Support/ResourceQuery.php"
    - "app/Http/Controllers/Concerns/**"
    - "app/Onboarding/**"
    - "app/Imports/**"
    - "resources/js/components/data-table*.tsx"
    - "resources/js/lib/**"
---

# The UX layer

## A list is server-driven; the client never filters

Search, filters, sorting, pagination, column selection, bulk selection and CSV
export all happen in the query. A controller lists by using
`App\Http\Controllers\Concerns\ListsResources`, which builds on
`App\Support\ResourceQuery` and applies the organization scope before anything
else. Never filter a collection in React and never hand the frontend rows it is
expected to hide.

## Scoping is a query, not an if-check

`findListed()` looks a record up inside the organization's own query, so an id
from another organization is a 404. A post-fetch `if ($record->organization_id
!== ...)` is not a control and does not count as one. The export path is the
same query: a filtered CSV that drops the scope is a bulk leak.

## Bulk actions carry `allMatching`, not a page of ids

"Select everything that matches" is a predicate the server re-runs, not a list
the browser sends. The request key is `allMatching`; the server rebuilds the
filtered, scoped query from it.

## URL state round-trips

Filters, sort, page and columns live in the URL and survive a reload and a share.
`ResourceQuery::fromRequest()` and `ResourceQuery::toQueryParameters()` are the
only two places that encoding exists. Change one without the other and a shared
link stops reproducing the screen.

## Forms validate live through Precognition

A form that validates on the server validates the same rules on the way in.
Never duplicate a rule in TypeScript; the parity gate fails when a precognitive
route drifts from its form request.

## Untrusted files are scanned before they are promoted

An upload lands in a temporary record, is scanned, and only then becomes a real
file. `app/Imports` runs registry-driven batches; a machine without a scanner
gets `NullScanner` and promotes on trust, which `app:doctor` reports.

## Never hard-code copy, colour or duration

User-facing strings come from `lang/*` through `resources/js/lib/i18n.ts` and a
key must exist in every locale. Colours come from the brand tokens. Durations
come from `resources/js/lib/motion.ts`, which answers zero under reduced motion.

The long version is in `wiki/domains/ux-list-kit.md`,
`wiki/domains/ux-filters-and-saved-searches.md` and the other `ux-*` pages.
