---
title: Scoped search and the command palette
status: current
supersedes: []
code_refs:
    - app/Actions/SearchResources.php
    - app/Http/Controllers/SearchController.php
    - app/Data/SearchGroupData.php
    - app/Data/SearchResultData.php
    - resources/js/components/command-palette.tsx
    - tests/Feature/Controllers/SearchEndpointScopeTest.php
    - tests/Mutations/phase1-policy.patch
updated: 2026-08-31
---

# Scoped search and the command palette

One endpoint searches every registered resource at once, and one keyboard
shortcut opens it. `⌘K` (or `Ctrl+K`) is the whole discovery story for this kit:
a new resource appears in the palette because it is in the registry, not because
anyone edited the palette.

## What decides what comes back

`App\Actions\SearchResources` runs two gates, and they are different gates.

The **organization gate** is the resource's own `scopedQuery()`. Another
organization's rows are excluded by a where clause and never loaded, which is
the same rule the rest of the kit follows — see [[domains/multi-tenancy]] and
[[architecture/fail-closed-scoping]].

The **permission gate** is the resource's policy, checked once per resource. A
member who may not view a resource gets no group for it. It is not a 403,
deliberately: refusing the whole search because one of eight resources is out of
reach would make the palette useless to everyone but an owner.

Each group is capped at five hits (`SearchResources::PER_GROUP`). A palette is a
shortcut; past a handful of rows the list screen is faster.

## The endpoint

`App\Http\Controllers\SearchController` answers JSON, not an Inertia page. The
palette calls it on a debounce while someone types and never navigates to it.
The term is validated (`max:255`) and an empty term returns no groups rather
than the first page of everything.

## The palette

`resources/js/components/command-palette.tsx` draws every hit from the label,
description and URL the server sent. It knows nothing about any particular
resource. It debounces keystrokes into one request, keeps the results in a flat
list so `↑`/`↓` cross group boundaries, and renders
[[domains/ux-primitives]]'s empty state when nothing matches.

## The control, and the test that proves it

The policy check in `SearchResources` is what keeps one member's palette from
answering with records they may not read.
`tests/Feature/Controllers/SearchEndpointScopeTest.php` asserts it; the
committed mutation `tests/Mutations/phase1-policy.patch` removes the check and
the test goes red, which is how the test is known to be testing something. Run
both halves with:

```bash
bin/prove-control.sh phase1-policy SearchEndpointScope
```

## Related

- [[domains/ux-list-kit]] — the list screens the palette shortcuts to
- [[domains/resources]] — the registry every group comes from
