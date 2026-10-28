---
title: Code knowledge graphs
status: current
supersedes: []
code_refs:
    - .githooks/post-commit
    - .ai/rules/general.md
updated: 2026-10-29
---

# Code knowledge graphs

One index of this codebase, Laravel Brain, rebuilt after every commit by
`.githooks/post-commit` (wired up by `composer install`, nothing to run by hand).
It ships as a `require-dev` package and covers routes, controllers, models,
relationships, events, listeners, jobs, middleware, policies and DB operations
per method. It exists to cut the tokens an agent burns rediscovering structure
([[architecture/graph-before-grep]]). Its context exporter returns a budgeted
slice of a single request path:

```bash
php artisan brain:export-context --route=/settings/profile --budget=2000
```

That gives middleware, call chain, complexity hotspots and queries in a few
hundred words instead of opening the controller, form request, action and model.
There is an interactive viewer at `/_laravel-brain` in development. If the graph
is stale or missing, rebuild it with `php artisan brain:scan --no-interaction`.

graphify, code-review-graph and gitnexus were rebuilt by the same hook until
2026-10-29 and were dropped for lack of accuracy evidence; the reasoning is in
[[architecture/graph-before-grep]].

## Two decisions worth keeping

**It does not run as an MCP server.** An always-on MCP server re-sends its tool
schemas every conversation turn whether you use it or not, which is the opposite
of the point.

**The hook stays out of the way.** It skips during rebase, merge and cherry-pick,
so a history rewrite does not rescan once per replayed commit.

`.ai/rules/general.md` tells every agent when to use it, so the routing costs
nothing until a query is actually run.
