---
name: outcome
description: "Use when the user wants something to stay true rather than be fixed once (\"keep it crash-free\", \"never again\", \"stay green\", \"make sure this stays fixed\") or runs /outcome: turn a one-off fix into a standing outcome for this starter kit: one sentence an outsider can check, the command that decides it, proof that an agent can see the failure, locate the code and prove a fix on its own, an entry in todo/outcomes.status.json, and an optional schedule that keeps it true."
license: MIT
metadata:
    author: cogneiss
---

# Standing outcome

Ask what should happen after the task the user named is done, and work at that
level. "Fix this crash" becomes "keep the app crash-free". "Make this test pass"
becomes "keep main green".

## 1. State the outcome

One sentence an outsider can check, and the command that decides it. Exit 0
means it holds.

- "`composer verify` passes on main" → `composer verify`
- "No list page runs more queries at 500 rows than at 1" → the query-count test
- "Every claim in `todo/*.status.json` still holds" → `composer test:status`

## 2. Check the loop can run without a human

A standing outcome only runs unattended if an agent can do three things on its
own. For each, name what provides it here, or mark it missing.

| need   | question                                         | what this app has                                                                                                   |
| ------ | ------------------------------------------------ | ------------------------------------------------------------------------------------------------------------------- |
| see    | Can it fetch the failure signal itself?          | Boost `last-error`, `read-log-entries`, `browser-logs` locally; Sentry in production; `gh run list` for CI          |
| locate | Can it map a signal to the code that produced it? | the `release` on a Sentry event is the deployed git SHA (`services.sentry.release`)                                 |
| prove  | Can it reproduce and verify a fix independently? | `bin/worktree-setup` (own database), a failing Pest test first, `bin/prove-control.sh` for a security control       |

Production signals need access a fresh clone does not have: a `SENTRY_DSN`, and
a way for the agent to read Sentry (a Sentry MCP server or API token on the
developer's machine). Each missing need is a setup task, and it comes first. An
outcome missing any of the three is a reminder, not a loop.

## 3. Record it

Add an entry to `todo/outcomes.status.json`, creating it in the same shape as
the other `todo/*.status.json` files:

```json
{
  "created": "<YYYY-MM-DD>",
  "plan": "standing outcomes",
  "phases": [
    {
      "id": 1,
      "name": "<the sentence from step 1>",
      "status": "failing",
      "command": "<the check, exit 0 = holds>",
      "needs": { "see": "<source>", "locate": "<mapping>", "prove": "<how>" },
      "cadence": "daily"
    }
  ]
}
```

`status` is `passing` only after `command` ran and exited 0 in this session.
Unproven is `failing`. `composer test:status` re-runs every command in
`todo/*.status.json`, so the entry is checked with the rest.

## 4. Run the check once

Run `command` now and record `status`. If it fails, the first fix goes through
the `contract` skill.

## 5. Offer the schedule

Ask before creating one; both act without a human watching.

- Claude Code `/schedule` runs a cloud agent on cron. It only sees what is
  committed and reachable from the cloud, so local signals (Boost logs, the
  local database) do not work there.
- Claude Code `/loop` runs in this session on this machine.
- Any agent: a cron entry that runs `composer test:status`.

Each run: check, and if it fails, reproduce, fix through the `contract` skill,
re-run the check, update `status`.
