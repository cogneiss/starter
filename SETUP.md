# Development Setup

## Prerequisites

Install required tools:

```bash
# graphify - knowledge graph extraction
pip install graphifyy

# code-review-graph - impact analysis + MCP tools
pip install code-review-graph
```

## Automatic Setup

After cloning, run:

```bash
git config core.hooksPath .githooks
```

This configures git to use committed hooks in `.githooks/`. Hooks are shared with all devs—no manual setup needed.

## What's Automated

Post-commit hooks run automatically after every commit:

- `graphify --update` — incremental knowledge graph rebuild
- `code-review-graph index` — SQLite call-graph rebuild

Both run in the background (non-blocking).

## MCP Tools (Optional)

For Claude Code to access graph tools directly:

```bash
# Terminal 1: Start graphify MCP server
graphify --mcp

# Terminal 2: Start code-review-graph MCP server
code-review-graph --mcp
```

Then in Claude Code: toggle `github-on` and `review-on` in settings to wire these in.

## Outputs

- `graphify-out/` — knowledge graph (JSON, HTML, report)
- `.code-review-graph/` — impact analysis (SQLite DB)
