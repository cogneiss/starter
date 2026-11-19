---
title: Decision log, CI and quality gates
status: current
supersedes: []
code_refs:
    - .githooks/pre-commit
    - .githooks/pre-push
    - .claude/hooks/require-contract.sh
    - .claude/settings.json
    - .claude/agents/auditor.md
    - .github/workflows/ci.yml
    - .github/workflows/claude.yml
    - .github/workflows/auditor.yml
    - .github/pull_request_template.md
    - bin/prove-contract-hook.sh
updated: 2026-11-19
---

# Decision log, CI and quality gates

Thirteen phases, all `passing` in `todo/ci-quality.status.json`: the CI fan-out
topology, secret and dependency scanning, the autofix bot, dead code and unused
dependencies, the local test loops, test impact analysis, the accessibility suite,
the Postgres nightly, mutation testing, detaching from the fork plus
release-please, the SBOM on release, documentation, and the full gate. It records
release `v0.2.0` with `sbom.json` as the asset.

## The decisions

- **One install, then fan out.** `setup` builds once and uploads the build as a
  tar so binaries stay executable; `tests`, `security`, `static` and `a11y` run in
  parallel off that artifact ([[operations/ci]]).
- **Blocking means deterministic.** A gate that flakes gets ignored, so mutation
  score, Postgres and performance scores report instead of blocking
  ([[architecture/fast-blocking-gates]]).
- **A finding is baselined with a reason, never silenced by loosening the tool.**
  `phpstan-deadcode-baseline.neon`, `composer-unused.php` and `knip.json` each
  carry the accepted findings and why ([[operations/tooling]]).
- **Test impact analysis is local only.** CI has no reason to trust an impact map
  it did not build ([[operations/testing]]).
- **Accessibility is blocking at level 3.** A starter kit's accessibility defects
  are inherited by every application built on it.
- **The autofix bot is hard-skipped on forks.** That guard stops a fork's pull
  request running repo-controlled steps against a branch we do not own; it is a
  security control, not a convenience ([[operations/ci]]).
- **Both copyright lines in `LICENSE` stay.** This kit is a fork of Nuno Maduro's
  starter kit; removing the original line is an MIT violation.

Test sharding, Lighthouse budgets, `composer-require-checker` and `.env` drift
checking were considered and left out, reasons in [[decisions/not-included]].

## Splitting `composer verify`

Splitting `composer verify` was left out earlier and has since been done, in a
narrower form. The commit gate and the push gate now have different jobs.

- **Commit: fast, scoped.** `.githooks/pre-commit` fixes staged files, runs
  phpstan and `tsc` over the whole project when PHP or TypeScript is staged, and
  runs Pest only on the staged test files and the mirrored test of each staged
  source file. Measured: clean commit 8s, docs-only commit 1s.
- **Push: complete.** `.githooks/pre-push` still runs all of `composer verify`.
  Measured: full push 113s. A green run on a clean tree stamps the tree hash in
  `verified-tree` inside the git directory, and a later push of the same tree
  skips the run: skip push 1s. A tree that differs only in `todo/*.status.json` also skips; see [[operations/contributing]].

The stamp is by tree, not by commit, so it cannot excuse a content change, and it
is written only after a green gate on a clean working tree. A failed run deletes
it first. The cost is that a source change with no mirrored test reaches the push
gate before any test sees it. That is accepted: the push still runs everything.

The only local bypass is skipping the hooks, and `guard-gates.sh` refuses it for
an agent; the real gate is CI, which runs `composer verify` on every pull request
([[operations/ci]]).

## Switching CI on

CI and the auditor are built and idle. Nothing in this repository triggers
either. When you decide to spend the minutes:

1. Re-enable Actions in the repository settings if they are off, and clear the
   runs left queued from before the pause (listed in `todo/known-issues.status.json`).
2. Add the repository secret `CLAUDE_CODE_OAUTH_TOKEN`, created with
   `claude setup-token`. `claude.yml` and `auditor.yml` both read it.
3. Set the repository variable `AUDITOR_ENABLED` to `true`. Without it the
   auditor job is skipped, so it costs nothing until this step.
4. Open a pull request with at least one real row under `## Acceptance criteria`.
   `auditor.yml` fails fast when the body has none, and it ignores forks.
5. Once `ci` is green on that pull request, keep the `ci` job ids as the required
   checks in the ruleset.

The auditor is advisory: it comments with a PASS or FAIL per criterion and does
not block the merge. It runs the prompt in `.claude/agents/auditor.md`.
