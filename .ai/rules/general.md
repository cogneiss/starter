---
paths:
  - '**'
  - '{AGENTS,CLAUDE,GEMINI}.md'
---

# General

## Query the knowledge graph before grepping the codebase
This repo keeps four prebuilt code graphs, rebuilt automatically by `.githooks/post-commit`. Use them before fanning out with grep/glob — they answer structural questions in one call instead of dozens of file reads.

**Start here for anything Laravel-shaped.** The other three graphs are language-level: they see symbols and calls, not framework wiring. Laravel Brain is the only one that knows a route binds to a controller, a model has relationships, an event has listeners, a job is queued. Working on a request path? Pull its slice first:

```bash
php artisan brain:export-context --route=/settings/profile --budget=2000
```

That returns the route's method, middleware, call chain (depth ≤ 3), complexity hotspots, and DB operations — a few hundred words instead of opening the controller, the form request, the action, and the model. Other entry points: `--node=<id>` to focus one symbol, `--format=json` for machine use, `--budget=N` to cap the spend.

The URI needs its leading slash. `--route=/dashboard` scopes; `--route=dashboard` silently falls back to a full-project dump — check the `Focal:` line on the second line of output says the route you asked for, not `Full project summary`, before trusting the size.

Do not run `brain:generate-rules`. It writes a project snapshot into `CLAUDE.md` and the other agent config files; that snapshot goes stale on the next commit and is re-read on every turn, which is the cost this whole setup exists to avoid. This file is the routing surface.

Then the language-level graphs:

- Architecture, "how does X relate to Y", "where does this concept live": `graphify query "<question>"` (artifact: `graphify-out/graph.json`; `graphify-out/GRAPH_REPORT.md` is the human summary).
- Blast radius / callers / "what breaks if I change this": `code-review-graph impact <symbol>` and `code-review-graph query`. Also `search`, `architecture`, `dead-code`, `large-functions` (DB: `.code-review-graph/graph.db`).
- Execution flows, a symbol's full 360 (callers + callees + processes), or mapping a diff to affected flows: `gitnexus context <symbol>`, `gitnexus impact <symbol>`, `gitnexus trace <from> <to>`, `gitnexus detect-changes`, `gitnexus cypher '<query>'` (artifact: `.gitnexus/`).

Use the gitnexus **CLI**, never its MCP server — an always-on MCP server re-sends its tool schemas on every turn and every subagent spawn; the CLI costs nothing until invoked. This is why `gitnexus analyze` runs with `--skip-agents-md` in the hook: left alone it rewrites `AGENTS.md`/`CLAUDE.md` to point at the MCP tools.

All four output dirs are gitignored and machine-local — never commit them, and never read the raw `graph.json`/`graph.db`/`.gitnexus`/`storage/app/laravel-brain` files into context, always go through the CLI. If a command reports a stale or missing graph, rebuild with `php artisan brain:scan --no-interaction`, `graphify update .`, `code-review-graph update`, `gitnexus analyze . --skip-agents-md`.

## Re-sync GEMINI.md by hand after regenerating Boost guidelines
Boost no longer renders GEMINI.md, even though `gemini` is still listed in `boost.json`. `boost:update` and `boost:install` refresh AGENTS.md and CLAUDE.md but leave GEMINI.md untouched, so it silently drifts (it had gone stale enough to tell agents to run `npm run build` in a bun project).

After regenerating guidelines, always run: `cp AGENTS.md GEMINI.md`

Also pass both flags when reinstalling: `boost:install --guidelines --skills`. `--guidelines` alone drops the "Skills Activation" section from AGENTS.md and CLAUDE.md.
