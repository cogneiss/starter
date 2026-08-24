---
title: Why agents query a graph before grepping
status: current
supersedes: []
code_refs:
    - .githooks/post-commit
    - .ai/rules/general.md
updated: 2026-08-24
---

# Why agents query a graph before grepping

Four code indexes are rebuilt after every commit by `.githooks/post-commit`. An
agent asking a structural question is meant to query one of them instead of
fanning out with grep.

## The economics

"What breaks if I change this" answered by grep means opening the controller, the
form request, the action, the model, and then their callers — dozens of file
reads, each one paid for in every later turn of the same conversation because the
context is re-sent. The same question against Laravel Brain is one command and a
few hundred words:

```bash
php artisan brain:export-context --route=/settings/profile --budget=2000
```

Laravel Brain comes first among the four because it is the only one that knows
framework wiring: a route binds to a controller, a model has relationships, an
event has listeners. The other three are language-level and see symbols and calls.

## Why none of them run as an MCP server

An always-on MCP server re-sends its tool schemas on every conversation turn and
on every subagent spawn, whether or not it is used. That is a fixed cost paid to
avoid a variable one, which inverts the point of the whole setup. The CLIs cost
nothing until invoked, which is why `.ai/rules/general.md` is a routing table
rather than a set of connected servers, and why `gitnexus analyze` runs with
`--skip-agents-md` in the hook — left alone it rewrites the agent config files to
point at its MCP tools.

The same argument rules out `brain:generate-rules`: it writes a project snapshot
into `CLAUDE.md`, which goes stale on the next commit and is re-read on every
turn.

Commands, artifacts and rebuild instructions are in
[[operations/code-knowledge-graphs]].
