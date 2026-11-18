---
name: deepen
description: "Use before building anything non-trivial in this starter kit (a feature, a cross-layer refactor, a performance goal, a fix with unclear blast radius), and on /deepen, \"a deeper version\" or \"be more ambitious\": rewrite the task the way a top professional in its field would approach it: the practice the request leaves out, the rawest material in this app to start from, the instruments to run, and a check outside the model that shows the result is right. Feeds the contract skill. Skip it for a one-file change with an obvious test."
license: MIT
metadata:
    author: cogneiss
---

# Deepen a task

Take the task the user named and answer this, word for word as the user would
ask it:

> I want a deeper version of this task, not a bigger one. Before rewriting it:
> (1) say what a top professional in this field would do here that I'm not
> asking for; (2) name the rawest material they would start from instead of
> what has already been digested for me; (3) name the specific methods and tools
> they would use; (4) say what, other than you or me, would show the result is
> right. Then rewrite my prompt to ask for exactly that.

## Rules for the four answers

- **Deeper, not bigger.** Same goal, same scope. An answer that adds a feature
  gets cut.
- **(2) names material that exists here.** Look before you name it. In this app
  the raw sources are rows (Boost `database-query`), failures (Boost
  `last-error`, `read-log-entries`), the browser console (Boost
  `browser-logs`), wiring (`php artisan brain:export-context --route=/<uri>`),
  history (`git log -p -- <file>`), and the checks in `todo/*.status.json`. If
  the rawest material is not reachable from this machine (production errors,
  real traffic), say which access is missing; that gap is part of the answer.
- **(3) names instruments you will run.** A SQL query, a profiler, a query
  count, a benchmark, a statistical test, a reproduction script,
  `bin/prove-control.sh` for a security control. A markdown table where a query
  would answer means the vocabulary changed and the method did not.
- **(4) is a runnable check.** A Pest test, a benchmark with a number, held-out
  data, a third-party validator. Not you re-reading your work, not the user
  eyeballing it. Where the result has a number, set a hard target (200ms, not
  5s) and treat it as reachable.

## Output

Started by the user (/deepen, "a deeper version", "be more ambitious"):

1. The four answers, one short paragraph each.
2. The rewritten prompt in a fenced block, ready to paste.
3. Stop. Do not start the work. Once the user approves, the rewritten prompt
   goes through the `contract` skill and answer (4) becomes its criteria.

Started on its own before a build:

1. Show the user the four answers, two lines each.
2. Go straight into the `contract` skill with answer (4) as its criteria. Ask
   one question only if an answer widens the scope or needs access you lack.
