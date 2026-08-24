---
title: CI topology
status: current
supersedes: []
code_refs:
    - .github/workflows/tests.yml
    - .github/workflows/nightly.yml
    - .github/workflows/mutation.yml
    - .github/workflows/lint-autofix.yml
    - .github/workflows/tia-baseline.yml
    - .github/workflows/release-please.yml
updated: 2026-08-24
---

# CI topology

Six workflows. One of them blocks.

## `tests.yml` — the blocking one

A `setup` job installs Composer and Bun dependencies, builds the frontend, and
uploads the build as an artifact. Everything after it downloads that artifact and
runs in parallel:

| Job        | Runs                                                                         |
| ---------- | ---------------------------------------------------------------------------- |
| `tests`    | `composer test` — lint, type coverage, PHPStan, suite at `--exactly=100.0`   |
| `security` | gitleaks over the directory and over git history, plus `composer test:audit` |
| `static`   | `composer test:dead-code`, `composer test:deps`, `composer test:knip`        |
| `a11y`     | `composer test:a11y`, uploading screenshots on failure                       |

Fanning out this way costs a job slot, not a second install. The shared build is
uploaded as a tar so binaries stay executable through the artifact round trip.

## The other five

| Workflow             | Trigger              | Does                                               |
| -------------------- | -------------------- | -------------------------------------------------- |
| `nightly.yml`        | cron `17 3 * * *`    | `composer test:pgsql` against a `pgsql` service    |
| `mutation.yml`       | schedule             | the mutation score, reporting only                 |
| `tia-baseline.yml`   | schedule             | `composer test:tia-seed`, recording the impact map |
| `lint-autofix.yml`   | branch push, in-repo | formats and commits back; hard-skipped on forks    |
| `release-please.yml` | push to `main`       | keeps the release pull request open                |

Two of those carry warnings worth repeating. The fork guard in
`lint-autofix.yml` is a security control — it stops a fork's pull request running
repo-controlled steps against a branch we do not own. And nothing here may add
`--dirty` or `--tia`: CI runs the full suite, because it has no reason to trust an
impact map it did not build.

Why the split exists at all is [[architecture/fast-blocking-gates]]. The commands
themselves are in [[operations/commands]].
