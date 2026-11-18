---
name: triage-errors
description: "Use when an exception, a 500, a failed job, a browser console error or a Sentry issue in this starter kit needs fixing. A loop from the raw signal to a proven fix: read the stack trace from Boost or Sentry, map it to the exact commit through the release SHA, reproduce it as a failing Pest test, fix the root cause, and prove the test goes from red to green. Read it before guessing at a fix from an error message."
license: MIT
metadata:
    author: cogneiss
---

# Triage an error

Work from the raw signal, never from a paraphrase of it. A fix is done when a
test that failed for the reported reason now passes.

## 1. See: fetch the raw signal

- Local exception: Boost `last-error`, then `read-log-entries` for the lines
  around it.
- Frontend: Boost `browser-logs`.
- Production: the Sentry event. Its `release` is the deployed git SHA
  (`config/services.php`, `services.sentry.release`), and its context holds
  `organization_id`, `user_id` and a `request_id` shared by every error in that
  request (`app/Support/Reporting/ReportsErrors.php`). There is no request body
  or token in it, by design.
- CI: `gh run view <id> --log-failed`.

Copy the exception class, message and the first application frame. If you
cannot get the raw trace, say which access is missing instead of guessing.

## 2. Locate: map it to code

- Production: `git log --oneline <release>..HEAD -- <file of the top app frame>`
  shows whether the code has changed since the failing deploy. Read the file at
  the failing release with `git show <release>:<file>` before reading the
  current one.
- Wiring the trace does not show (which listener, which job, which route):
  `php artisan brain:export-context --route=/<uri> --budget=2000`.
- Rows involved: Boost `database-query`, read-only.

## 3. Reproduce: a failing test first

Read the `testing-best-practices` skill. Write the smallest Pest test that fails
for the reported reason: the same exception class or the same wrong response.
Run it and confirm it fails that way before touching the code. A tenant-scoped
failure needs the `org-access` skill; a failure in the platform ops layer needs
the `platform-ops` skill.

If the bug needs its own database or a long-running reproduction, run it in a
worktree: `bin/worktree-setup` gives each worktree its own database.

## 4. Fix the root cause

Fix where every caller routes through, not only the path the error names. Grep
the callers of the function you change.

## 5. Prove it

- The test from step 3 now passes, and the tests touching the changed files
  pass.
- A security control: `bin/prove-control.sh <patch> <filter> <case>` shows the
  test goes red with the control disabled.
- Report the test name and the before and after output lines.

If the same class of error could recur, offer the `outcome` skill to keep it
fixed.
