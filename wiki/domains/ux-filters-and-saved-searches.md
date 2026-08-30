---
title: Faceted filters, URL state and saved searches
status: current
supersedes: []
code_refs:
    - app/Support/ResourceFilter.php
    - app/Enums/FilterType.php
    - app/Data/ResourceFilterData.php
    - app/Models/SavedSearch.php
    - app/Http/Controllers/SavedSearchController.php
    - app/Policies/SavedSearchPolicy.php
    - resources/js/components/data-table-filters.tsx
    - resources/js/components/data-table-views.tsx
    - tests/Feature/Resources/UrlRoundTripTest.php
    - tests/Feature/Resources/SavedSearchScopeTest.php
    - tests/Mutations/phase4-serializer.patch
    - tests/Mutations/phase6-predicate.patch
updated: 2026-08-31
---

# Faceted filters, URL state and saved searches

A filtered list is a URL. Copy it into a message, reopen it next week, save it
as a view — all three are the same thing, because the address bar is the only
place the state lives.

## What a filter is

`App\Support\ResourceFilter` declares a key, a label, a type and a column
(optionally `relation.column` through a `belongsTo`). The type — the cases of
`App\Enums\FilterType` — decides three things at once: how a value is read out
of the query string, how it narrows the query, and which control the table
draws.

| Type           | Reads as                 | Counted in facets |
| -------------- | ------------------------ | ----------------- |
| `select`       | one allowed option       | yes               |
| `multi-select` | a set of allowed options | yes               |
| `boolean`      | a boolean                | yes               |
| `range`        | `min`/`max` numbers      | no                |
| `date-range`   | `from`/`to` dates        | no                |

Nothing about a filter is decided by the page, so two screens listing the same
resource cannot disagree about what `f[status]=active` means.

Normalising is allowed to fail. A value whose shape makes no sense for its type
returns null and the filter is simply not applied, so a hand-edited query string
produces an unfiltered list rather than a 500.
`tests/Feature/Resources/MalformedFilterTest.php` is the record of that.

## Facets

Facet counts are taken from the query **before** the filters narrow it, so an
option can say how many rows it would leave rather than how many the current
selection already left. A count is scoped like everything else; a facet that
counted across organizations would leak row counts without ever showing a row,
which `tests/Feature/Resources/FacetScopeTest.php` covers.

## URL round-trip

Serialisation is symmetric by construction: `ResourceFilter::serialize()` writes
what `normalize()` reads. `tests/Feature/Resources/UrlRoundTripTest.php` takes a
view, writes its URL, reopens it and asserts the same rows, sort, page and
filter values come back. `tests/Mutations/phase4-serializer.patch` breaks the
serializer and that test is what notices.

## Saved searches

A saved search is a stored `ResourceQuery` with a name, and it belongs to one
person in one organization. `SavedSearch::ownedBy()` is where every read and
every write starts: the organization arrives from the global scope, the person
from the predicate. Someone else's saved search — in this organization or
another — is **absent from the result set** rather than found and then refused,
so the id of a search belonging to a colleague answers 404.

One search per resource may be marked default. It applies only when the request
says nothing about the list itself: if any of `ResourceQuery::PARAMETERS` is
present, the person has spoken and the default stays out of the way. A default
whose schema no longer matches the resource degrades to the plain list instead
of erroring — resources change, and a stored view is not a migration
(`tests/Feature/Resources/SavedSearchDegradesTest.php`).

## The controls, and the tests that prove them

```bash
bin/prove-control.sh phase4-serializer UrlRoundTrip
bin/prove-control.sh phase6-predicate SavedSearchScope
```

## Related

- [[domains/ux-list-kit]] — the query object these filters narrow
- [[domains/multi-tenancy]] — the global scope behind `ownedBy()`
