---
paths:
    - ".github/**"
    - "composer.json"
    - "package.json"
---

# CI and quality gates

## Never lower a threshold to make a gate pass

Not `--exactly=100.0`, not `--min=100`, not the mutation `--min=80`. A red gate means the code is wrong or the finding is acceptable — fix the code, or record the finding in the tool's baseline (`phpstan-deadcode-baseline.neon`, `composer-unused.php`, `knip.json`) with a comment saying why. A loosened threshold silently stops protecting everything else it covered.

## Blocking gates are fast, deterministic and about the diff

Everything else goes on a schedule and reports. That is why the Postgres run, the mutation score and the SBOM are scheduled, not blocking. A gate people cannot predict gets routed around: branch protection gets loosened, or the team learns which rerun makes red go away. Do not promote a scheduled job to a required check without making it deterministic first.

## The fork guard in `lint-autofix.yml` is a security control

The autofix job checks that the head repository is this repository before it runs. Removing or weakening that lets a pull request from a fork run repo-controlled workflow steps against a branch we do not own. Do not touch it.

## CI runs the full suite; TIA is local only

`tests/Pest.php` calls `pest()->tia()->locally()`. CI has no reason to trust an impact map it did not build, so every CI job runs everything. Never add `--dirty` or `--tia` to a workflow.

## Conventional commit prefixes are required

`.githooks/commit-msg` enforces them and release-please reads them to pick the next version and write the changelog. `feat:` bumps the minor, `fix:` the patch, `!` or a `BREAKING CHANGE:` footer the major (the minor while below 1.0.0).

## Both copyright lines in `LICENSE` stay

This kit is a fork. Removing `Copyright (c) Nuno Maduro` is an MIT violation, not a tidy-up.
