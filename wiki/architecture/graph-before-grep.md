---
title: Why agents query a graph before grepping
status: current
supersedes: []
code_refs:
    - .githooks/post-commit
    - .ai/rules/general.md
    - .claude/settings.json
updated: 2026-10-29
---

# Why agents query a graph before grepping

Laravel Brain's index is rebuilt after every commit by `.githooks/post-commit`.
An agent asking a structural question is meant to query it instead of fanning out
with grep.

## The economics

"What breaks if I change this" answered by grep means opening the controller, the
form request, the action, the model, and then their callers — dozens of file
reads, each one paid for in every later turn of the same conversation because the
context is re-sent. The same question against Laravel Brain is one command and a
few hundred words:

```bash
php artisan brain:export-context --route=/settings/profile --budget=2000
```

Laravel Brain earns its place because it knows framework wiring: a route binds
to a controller, a model has relationships, an event has listeners.

## Why the other three graphs were dropped

Until 2026-10-29 the hook also rebuilt three language-level graphs: graphify,
code-review-graph and gitnexus. They were removed because none had evidence of
making agents more accurate on this codebase, while each added an install step,
a hook stage, ignored artifacts and, for gitnexus, six committed skill folders.
They saw symbols and calls, which the PHP and TypeScript language servers enabled
in `.claude/settings.json` already answer. Laravel Brain was kept because it
answers questions those tools cannot.

## Why it does not run as an MCP server

An always-on MCP server re-sends its tool schemas on every conversation turn and
on every subagent spawn, whether or not it is used. That is a fixed cost paid to
avoid a variable one, which inverts the point of the whole setup. The CLIs cost
nothing until invoked, which is why `.ai/rules/general.md` routes agents to the
CLI rather than to a connected server.

The same argument rules out `brain:generate-rules`: it writes a project snapshot
into `CLAUDE.md`, which goes stale on the next commit and is re-read on every
turn.

Commands, artifacts and rebuild instructions are in
[[operations/code-knowledge-graphs]].
