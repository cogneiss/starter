---
title: The server-driven list kit
status: current
supersedes: []
code_refs:
    - app/Http/Controllers/Concerns/ListsResources.php
    - app/Support/ResourceQuery.php
    - app/Data/ResourceListData.php
    - app/Resources/ResourceColumn.php
    - app/Actions/ExportResource.php
    - app/Actions/ApplyBulkAction.php
    - resources/js/components/data-table.tsx
    - resources/js/components/data-table-body.tsx
    - resources/js/components/data-table-export.ts
    - resources/js/components/data-table-preferences.ts
    - resources/js/components/data-table-query.ts
    - resources/js/components/data-table-visits.ts
    - tests/Unit/Support/ResourceQueryTest.php
    - tests/Feature/Controllers/ListsResourcesTest.php
    - tests/Feature/Resources/CsvExportScopeTest.php
    - tests/Browser/DataTableTest.php
    - tests/Mutations/phase3-scope.patch
    - tests/Mutations/phase5-export-scope.patch
updated: 2026-11-18
---

# The server-driven list kit

Search, sort, paginate, choose columns, act on a selection, download the result.
Every list screen in this kit does all of that through one trait and one query
object, so a new index page is a controller that calls `listResource()` and a
page that renders `<DataTable>`.

The server decides everything. The table component holds no query state of its
own: it reads what the server sent and puts the next query in the address bar
(`resources/js/components/data-table-visits.ts` makes the visit,
`resources/js/components/data-table-query.ts` writes the parameters and leaves
out the defaults). That is what makes a list view linkable — see [[domains/ux-filters-and-saved-searches]].

## Where a list starts

`App\Http\Controllers\Concerns\ListsResources::listResource()` begins at the
resource's `scopedQuery()`. The organization is a where clause on the query the
database runs, not a check on rows that came back. So a record from another
organization is not hidden from the list — it is not in the result set, and
`findListed()` cannot reach it either. **A foreign id is a 404 before any policy
is consulted**, the same as an id that never existed; the two are
indistinguishable from outside, which is the point.

`listResource()` also accepts a resource instance in place of the registry key,
which is what lets the super-admin area reuse it across organizations rather
than one at a time. There it runs with no organization context at all, so the
saved-views lookup is simply skipped instead of scoped to nothing — see
[[operations/ops-admin-area]] for that control plane.

## Disbelieving the query string

`App\Support\ResourceQuery` is what a request asked for after none of it was
believed. The sort column is checked against the resource's own allowlist, the
page size against `PER_OPTIONS` (10, 25, 50, 100), the page against arithmetic.
`per=100000` is rounded down rather than handed to the database. A hostile query
string produces a boring list — never an error page, never a column the screen
was not meant to expose.

The keys a list owns are fixed: `q`, `sort`, `dir`, `page`, `per`, `f`.

## Search

`ResourceQuery::applyTerm()` matches the `q` term against the resource's
`searchable()` columns, and the whole clause sits in one grouped `where` so it
cannot escape the organization scope. On Postgres it is full-text word-prefix
matching. Every word of the term must start a word in one searchable column,
in any order, so `acme john` finds `John@acme.test`. Punctuation and whitespace
split words, which is why `user@gmail.com` can be found by `gmail`. The split
uses the `WORD_BREAKS` list in `ResourceQuery` instead of a locale character
class, so an accented letter stays inside its word under any database
collation. A fragment from the middle of a word no longer matches: `ohn` does
not find `John`. A term with no letters or digits, such as `%` or `@`, has no
words to search for, so it falls back to the literal substring match. Every
other database driver uses that case-insensitive substring `like` for every
term. No index ships. The code comment on
`applyTerm()` names the ceiling: Postgres scans the table, and a GIN expression
index over the same `to_tsvector` expression is the upgrade when a table grows.
`tests/Unit/Support/ResourceQueryTest.php` holds the cases.

## Columns

`App\Resources\ResourceColumn::visibleTo()` filters the column set by the
viewer's abilities, and the export uses the same call, so a column somebody may
not see on screen is not in their CSV either. Which columns a person hid and how
wide the table is are per-user comfort settings and live in `localStorage`, not
in the URL and not in the database — see [SETUP.md](../../SETUP.md). The key
is the page path plus the signed-in user's id, and a stored value of the wrong
shape is discarded rather than trusted (`resources/js/components/data-table-preferences.ts`,
with the key built in `resources/js/components/data-table.tsx`).

While a search, sort or page change is in flight, only the table body is busy:
it carries `data-pending` and `aria-busy` (`resources/js/components/data-table-body.tsx`),
and the toolbar stays usable. `resources/js/components/data-table-visits.ts`
holds the busy state for at least 600 ms so a fast response does not flicker. Each
new request cancels the hold left over from the one before, otherwise that old
timer would clear the busy state while the new request is still loading.
`tests/Browser/DataTableTest.php` checks that only the body went busy, on each
step.

## Bulk actions

`App\Actions\ApplyBulkAction` runs one action over a selection, one record at a
time, and every record goes through the same gate the single-record path uses.
Checking the ability once and then looping would grant the whole selection on
the strength of its easiest record. A record the person may not touch comes back
named, rather than silently skipped or turned into a 500 that rolls back work
already done.

Reach is the other half. The selection is the current page unless the request
explicitly opts in to everything the filters match. A tick box labelled "select
all" cannot quietly mean the whole table.

## Export

`App\Actions\ExportResource` runs the same query the screen ran: the scoped
query, narrowed by the same term and the same filters, streamed with
`lazyById()` in chunks of 500. There is no filtering step after the read for
anyone to weaken later.

This is the sharpest edge in the kit. A screen that loses its scope leaks one
page; an export that loses its scope hands over the whole table as a file. That
is why the scope is part of the builder and why
`tests/Feature/Resources/CsvExportScopeTest.php` exists.

A list and its export share one URL: the same request with `Accept: text/csv`
streams the CSV (`ResourceQuery::wantsCsv()` reads the header; the browser side is `resources/js/components/data-table-export.ts`), so an export cannot drift away from the filters on screen.

Handing over a spreadsheet of an organization's data is itself worth a record,
so a scoped export writes an audit entry before the first row streams — see
[[operations/ops-audit-log]].

## The controls, and the tests that prove them

```bash
bin/prove-control.sh phase3-scope ListsResources
bin/prove-control.sh phase5-export-scope CsvExportScope
```

Each patch disables one control — the organization scope in `ListsResources`,
the organization scope in the export query — and the run fails unless the test
goes red with it applied and green again once it is restored.

## Related

- [[domains/ux-filters-and-saved-searches]] — filters, URL state, saved views
- [[domains/resources]] — the registry and the resource contract
- [[architecture/fail-closed-scoping]] — why the scope is a where clause
