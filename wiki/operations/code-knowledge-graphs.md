---
title: Code knowledge graphs
status: current
supersedes: []
code_refs:
    - .githooks/post-commit
    - .ai/rules/general.md
updated: 2026-08-24
---

# Code knowledge graphs

Four indexes of this codebase, rebuilt after every commit by
`.githooks/post-commit` (wired up by `composer install`, nothing to run by hand).
They exist to cut the tokens an agent burns rediscovering structure: "what breaks
if I change this" becomes one command instead of a file-by-file crawl
([[architecture/graph-before-grep]]).

| Tool              | Answers                                                                                                             | Ships as        |
| ----------------- | ------------------------------------------------------------------------------------------------------------------- | --------------- |
| Laravel Brain     | Routes, controllers, models, relationships, events, listeners, jobs, middleware, policies, DB operations per method | `require-dev`   |
| graphify          | "How does X relate to Y", where a concept lives                                                                     | optional, `pip` |
| code-review-graph | Blast radius, callers, dead code, large functions                                                                   | optional, `pip` |
| gitnexus          | Execution flows, a symbol's callers and callees, diff-to-flow mapping                                               | optional, `npm` |

Laravel Brain first: the other three are language-level and see symbols and
calls, while only Laravel Brain knows that a route binds to a controller or that
an event has listeners. Its context exporter returns a budgeted slice of a single
request path —

```bash
php artisan brain:export-context --route=/settings/profile --budget=2000
```

— giving middleware, call chain, complexity hotspots and queries in a few hundred
words instead of opening the controller, form request, action and model. There is
an interactive viewer at `/_laravel-brain` in development.

## Two decisions worth keeping

**None of the four runs as an MCP server.** An always-on MCP server re-sends its
tool schemas every conversation turn whether you use it or not, which is the
opposite of the point.

**The three optional tools degrade gracefully.** The hook detects a missing binary
and skips it, so a clone without `pip` or `npm` extras still commits normally. The
hook also skips during rebase, merge and cherry-pick, and honours
`GRAPHIFY_SKIP_HOOK`.

`.ai/rules/general.md` tells every agent which tool answers which question, so the
routing costs nothing until a query is actually run.
