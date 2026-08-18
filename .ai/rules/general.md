---
paths:
  - '**'
---

# General

## Query the knowledge graph before grepping the codebase
This repo keeps three prebuilt code graphs, rebuilt automatically by `.githooks/post-commit`. Use them before fanning out with grep/glob — they answer structural questions in one call instead of dozens of file reads.

- Architecture, "how does X relate to Y", "where does this concept live": `graphify query "<question>"` (artifact: `graphify-out/graph.json`; `graphify-out/GRAPH_REPORT.md` is the human summary).
- Blast radius / callers / "what breaks if I change this": `code-review-graph impact <symbol>` and `code-review-graph query`. Also `search`, `architecture`, `dead-code`, `large-functions` (DB: `.code-review-graph/graph.db`).
- Execution flows, a symbol's full 360 (callers + callees + processes), or mapping a diff to affected flows: `gitnexus context <symbol>`, `gitnexus impact <symbol>`, `gitnexus trace <from> <to>`, `gitnexus detect-changes`, `gitnexus cypher '<query>'` (artifact: `.gitnexus/`).

Use the gitnexus **CLI**, never its MCP server — an always-on MCP server re-sends its tool schemas on every turn and every subagent spawn; the CLI costs nothing until invoked. This is why `gitnexus analyze` runs with `--skip-agents-md` in the hook: left alone it rewrites `AGENTS.md`/`CLAUDE.md` to point at the MCP tools.

All three output dirs are gitignored and machine-local — never commit them, and never read the raw `graph.json`/`graph.db`/`.gitnexus` files into context, always go through the CLI. If a command reports a stale or missing graph, rebuild with `graphify update .`, `code-review-graph update`, `gitnexus analyze . --skip-agents-md`.
