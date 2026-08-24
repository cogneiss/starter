---
title: Decision log — CI and quality gates
status: current
supersedes: []
code_refs:
updated: 2026-08-24
---

# Decision log — CI and quality gates

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
checking were considered and left out — reasons in [[decisions/not-included]].
