---
paths:
  - '**'
---

# General

## Query the knowledge graph before grepping the codebase
This repo keeps two prebuilt code graphs, rebuilt automatically by `.githooks/post-commit`. Use them before fanning out with grep/glob — they answer structural questions in one call instead of dozens of file reads.

- Architecture, "how does X relate to Y", "where does this concept live": `graphify query "<question>"` (artifact: `graphify-out/graph.json`; `graphify-out/GRAPH_REPORT.md` is the human summary).
- Blast radius / callers / "what breaks if I change this": `code-review-graph impact <symbol>` and `code-review-graph query`. Also `search`, `architecture`, `dead-code`, `large-functions` (DB: `.code-review-graph/graph.db`).

Both output dirs are gitignored and machine-local — never commit them, and never read the raw `graph.json`/`graph.db` into context, always go through the CLI. If a command reports a stale or missing graph, rebuild with `graphify update .` and `code-review-graph update`.
