---
paths:
  - app/Actions/ApplyBulkAction.php
  - 'app/Http/Controllers/Concerns/**'
  - 'app/Imports/**'
  - 'app/Onboarding/**'
  - app/Support/ResourceQuery.php
  - 'resources/js/components/data-table*.tsx'
  - 'resources/js/lib/**'
---

# Actions Concerns Imports Onboarding Support Components Lib

- Scoping is query-level. `findListed()` resolves a record inside the organization's own query, so a foreign id is a 404. A post-fetch `if ($record->organization_id !== ...)` is not a control.
- The CSV export is the same scoped, filtered query as the list. An export that rebuilds its own query is a silent bulk leak; `tests/Feature/CrossOrgLeakTest.php` covers it.
- A bulk action carries either explicit ids or `allMatching`, a predicate `App\Actions\ApplyBulkAction` re-runs against the scoped query. Every record goes through the gate individually, never once for the whole selection.
- `ResourceQuery::fromRequest()` and `ResourceQuery::toQueryParameters()` are the only two places the URL encoding exists. Change both or a shared link stops reproducing the screen.
- An upload is written as a temporary record and scanned before it is promoted. With no scanner configured the kit uses `NullScanner` and promotes on trust, which `app:doctor` reports rather than hides.
