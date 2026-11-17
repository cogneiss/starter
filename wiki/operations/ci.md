---
title: CI topology
status: current
supersedes: []
code_refs:
    - .github/workflows/ci.yml
    - .github/workflows/nightly.yml
    - .github/workflows/mutation.yml
    - .github/workflows/screenshots.yml
    - .github/workflows/evals.yml
    - .github/workflows/codeql.yml
    - .github/workflows/tia-baseline.yml
    - .github/workflows/release-please.yml
    - .github/workflows/claude.yml
updated: 2026-11-18
---

# CI topology

Nine workflow files. One of them blocks.

## `ci.yml`, the blocking one

It runs on every pull request and on every push to `main`. A newer push to the
same pull request cancels the run in flight; a run on `main` always finishes.
Every action is pinned to a commit SHA, with the tag in a trailing comment.

| Job        | Runs                                                                                 |
| ---------- | ------------------------------------------------------------------------------------ |
| `setup`    | installs Composer and Bun dependencies, builds the frontend, uploads the build       |
| `verify`   | `composer verify`, the full local gate ([[operations/commands]])                     |
| `secrets`  | gitleaks over the directory and over git history                                     |
| `semgrep`  | `semgrep scan` with the `p/php` and `p/security-audit` rule packs, failing on a hit  |
| `autofix`  | Pint, Rector and `vp fmt`, committed back; skipped on pull requests from forks       |
| `a11y`     | `composer test:a11y` against the `setup` build, uploading screenshots on failure     |
| `advisory` | `php artisan wiki:audit` as a count table, plus `sloppy diff`; reports, never blocks |

`verify` runs its own install and build. Only `a11y` downloads the `setup`
artifact, which is uploaded as a tar so binaries stay executable through the
artifact round trip.

`verify`, `secrets` and `advisory` check out with `fetch-depth: 0`. `wiki:lint`
and `wiki:audit` compare a page's `updated:` date against `git log` for each
`code_refs` file, and a depth-1 clone finds nothing, so the stale-page rule would
pass silently. The gitleaks history scan walks every commit, so it needs them
too. The gate is `wiki:lint` inside `composer verify`. `advisory` only writes the
stale, undocumented and orphaned counts to the job summary and points at
`/document` ([[operations/documentation]]). It sets `continue-on-error`, so leave
it out of the required checks.

In `verify` the Rector and PHPStan caches live in the project-relative `tmp/`
directory rather than `/tmp`. PHPStan's dead-code run still caches under
`/tmp/phpstan-deadcode`.

`verify` and `a11y` both run against a `pgvector/pgvector:pg17` service rather
than plain `postgres:17`. The AI documents migration calls
`Schema::ensureVectorExtensionExists()`, which needs the extension to be
installable on the server. An image without it fails every run at migrate time
([[domains/ai-retrieval]]).

The `autofix` job carries the fork guard: it runs only when the pull request's
head repository is this repository. That line is a security control. It stops a
fork's pull request running repo-controlled steps against a branch we do not
own. Do not remove it, and do not swap the trigger for `pull_request_target`.
The same condition is false on a push to `main`, so the job only ever runs on a
pull request.

## The other eight

| Workflow             | Trigger                                         | Does                                               |
| -------------------- | ----------------------------------------------- | -------------------------------------------------- |
| `nightly.yml`        | cron `17 3 * * *`, manual                       | `composer test:sqlite`, the suite without pgvector |
| `mutation.yml`       | cron `41 4 * * 1`, manual                       | `composer test:mutate`, reporting only             |
| `screenshots.yml`    | cron `41 4 * * 1`, manual                       | `composer screenshots`                             |
| `evals.yml`          | cron `0 6 * * 1`, manual                        | `composer test:evals`                              |
| `codeql.yml`         | push to `main`, pull request, cron `17 4 * * 1` | CodeQL analysis                                    |
| `tia-baseline.yml`   | push to `main`                                  | `composer test:tia-seed`, recording the impact map |
| `release-please.yml` | push to `main`                                  | keeps the release pull request open                |
| `claude.yml`         | issue and review comments, new issues           | runs `anthropics/claude-code-action`               |

Nothing here may add `--dirty` or `--tia`: CI runs the full suite, because it has
no reason to trust an impact map it did not build.

Why the split exists at all is [[architecture/fast-blocking-gates]]. The commands
themselves are in [[operations/commands]].
