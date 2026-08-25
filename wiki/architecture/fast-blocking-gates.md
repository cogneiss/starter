---
title: Why blocking gates stay fast and deterministic
status: current
supersedes: []
code_refs:
    - .github/workflows/tests.yml
    - .github/workflows/nightly.yml
    - .github/workflows/mutation.yml
    - .ai/rules/ci.md
updated: 2026-08-25
---

# Why blocking gates stay fast and deterministic

A gate that blocks a pull request must be fast, deterministic, and about the
diff. Everything else runs on a schedule and reports.

## The failure mode

A blocking gate people cannot predict gets routed around. Not by argument — by
process. Branch protection gets loosened for "just this once", or the team learns
which rerun makes red go away and stops reading the output. Either way the gate
stops protecting anything, including the parts of it that were working.

That is the whole reason for the split in `.github/workflows/`:

- Blocking, on every pull request: `.github/workflows/tests.yml` — `composer test`,
  gitleaks, dependency audits, dead code, unused Composer packages, knip, and the
  axe-core accessibility suite.
- Scheduled, reporting: `.github/workflows/nightly.yml` (the suite on SQLite,
  minus vector search) and `.github/workflows/mutation.yml` (the mutation score).

Postgres is the blocking database, on a `pgvector/pgvector:pg17` service, because
that is what the kit defaults to and where retrieval lives
([[domains/ai-retrieval]]). The SQLite run is the scheduled one: it answers "does
a fork that drops Postgres still work", and its failures are driver differences
rather than anything wrong with the diff in front of you.

Mutation testing and the SQLite run are not scheduled because they matter less.
They are scheduled because a mutation score moves when nobody touched it, and a
driver difference is not a review comment. Promoting either to a required check
means making it deterministic first.

## The corollaries

- Never lower a threshold to make a gate pass. Not `--exactly=100.0`, not
  `--min=100`, not the mutation `--min=80`. Fix the code, or record the finding
  in the tool's own baseline (`phpstan-deadcode-baseline.neon`,
  `composer-unused.php`, `knip.json`) with a comment saying why. A loosened
  threshold silently stops protecting everything else it covered.
- CI runs the full suite. Test Impact Analysis is local only, because CI has no
  reason to trust an impact map it did not build. Never add `--dirty` or `--tia`
  to a workflow.
- The fork guard in `.github/workflows/lint-autofix.yml` is a security control,
  not a tidy-up target: it stops a pull request from a fork running
  repo-controlled workflow steps against a branch we do not own.

The job topology and what each one runs is in [[operations/ci]]. The reasoning
above is repeated in short form in `.ai/rules/ci.md`, which is what an agent
editing a workflow file actually loads.
