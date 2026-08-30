# UX layer guidelines

- The UX layer is `app/Support/ResourceQuery.php`, `app/Http/Controllers/Concerns/ListsResources.php`, `app/Onboarding`, `app/Imports`, `resources/js/components/data-table*.tsx` and `resources/js/lib`. Read `.ai/rules/ux.md` and load the `ux-kit` skill before editing any of it.
- Lists are server-driven. Search, filters, sorting, pagination, column visibility, saved views, bulk actions and CSV export all happen in the query, through `ListsResources`. Never filter a collection in React.
- Scoping is query-level. `findListed()` resolves a record inside the organization's own query, so a foreign id is a 404. A post-fetch `if ($record->organization_id !== ...)` is not a control.
- The CSV export is the same scoped, filtered query as the list. An export that rebuilds its own query is a silent bulk leak; `tests/Feature/CrossOrgLeakTest.php` covers it.
- A bulk action carries either explicit ids or `allMatching`, a predicate `App\Actions\ApplyBulkAction` re-runs against the scoped query. Every record goes through the gate individually, never once for the whole selection.
- `ResourceQuery::fromRequest()` and `ResourceQuery::toQueryParameters()` are the only two places the URL encoding exists. Change both or a shared link stops reproducing the screen.
- Forms validate live through Precognition against the same form request. Never restate a validation rule in TypeScript; `tests/Feature/PrecognitionParityTest.php` fails on drift.
- An upload is written as a temporary record and scanned before it is promoted. With no scanner configured the kit uses `NullScanner` and promotes on trust, which `app:doctor` reports rather than hides.
- User-facing strings live in `lang/<locale>/` and reach the browser through `resources/js/lib/i18n.ts`. A key present in one locale and missing from another fails the suite.
- Colours come from the brand tokens and durations from `resources/js/lib/motion.ts`, which returns zero under reduced motion. No hex literals and no hard-coded durations in components.
