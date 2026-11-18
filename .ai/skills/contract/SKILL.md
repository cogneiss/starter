---
name: contract
description: "Use when building anything multi-step in this starter kit where \"done\" is arguable: a feature, a refactor across layers, a performance goal, a fix whose blast radius is unclear. Criteria are written first, attacked by an independent auditor until each one has a runnable check, frozen in .work/feature_list.json, and the finished work is graded against them, not against a standard invented afterwards. Skip it for a one-file change with an obvious test."
license: MIT
metadata:
    author: cogneiss
---

# Contract loop

Build the task the user named. Criteria are frozen before any code is written,
and the work is graded against those frozen criteria.

State lives in `.work/` at the repo root (git-ignored). Once
`.work/feature_list.json` exists, `.claude/hooks/guard-gates.sh` asks a human
before `.work/contract.md` changes, so the contract cannot be rewritten to fit
the code.

## 1. Propose

Read the code the change touches. Write `.work/contract.md`:

```markdown
# Contract: <name>

## Scope
In: <what this covers>
Out: <what it explicitly does not>

## Criteria
| id | criterion | check |
|----|-----------|-------|
| c1 | <observable outcome> | <exact command, or file:line + what to see there> |

## Grading
Any criterion failing fails the item. No partial pass. Unverifiable scores 0.0.
```

Every criterion passes the disagreement test: two reasonable engineers reading
it and the finished code cannot disagree about whether it passed. A criterion
with no runnable check is not a criterion yet. A criterion about a quantity
(latency, query count, bundle size, error rate) names a hard number.

## 2. Negotiate

Send the contract to the auditor in **NEGOTIATE** mode. Fix what it rejects,
add what it says is missing, resend. Loop until it returns `ACCEPT`. Write no
implementation code in this phase.

The auditor is a separate agent with fresh context, never you re-reading your
own contract:

- Claude Code: the `auditor` subagent (`.claude/agents/auditor.md`).
- Other agents: start a fresh subagent whose instructions are
  [references/auditor.md](references/auditor.md).
- No subagents available: stop and ask the user to run the audit in a new
  session with that file as the prompt.

## 3. Freeze

On `ACCEPT`, write `.work/feature_list.json`:

```json
{
  "contract": ".work/contract.md",
  "items": [
    {
      "id": "c1",
      "name": "<criterion, one line>",
      "status": "failing",
      "verified_by": "<the exact command that decides it>",
      "notes": ""
    }
  ]
}
```

- `status` is exactly `failing` or `passing`. Not yet proven is `failing`.
- `verified_by` is a runnable command. If a criterion cannot be decided by a
  command, it is `manual: <file:line> — <exact observation>`, and only the
  auditor may mark it passing.
- Never delete or rewrite an item after the freeze. Add items only if the scope
  changed, and say so when you do.
- Never edit `status` yourself. Only step 5 changes it.

## 4. Build

Implement to the criteria in `.work/contract.md`, not to your reading of the
original request.

## 5. Grade

Send the frozen contract and the diff to the auditor in **GRADE** mode. Apply
its scores: `1.0` becomes `passing`, `0.0` becomes `failing` with the reason in
`notes`.

```bash
jq -e 'all(.items[]; .status == "passing")' .work/feature_list.json
```

Exit 0 means done. Exit 1 means fix the failing items and grade again, never by
editing the contract.

## Report

The ending state of `feature_list.json` (id, status, one-line reason for each
failing item), then anything the auditor filed under `OUT OF CONTRACT`.
