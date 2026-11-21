---
title: Releases
status: current
supersedes: []
code_refs:
    - .github/workflows/release-please.yml
    - .githooks/commit-msg
    - release-please-config.json
updated: 2026-11-21
---

# Releases

## Conventional commits, enforced before the branch

`feat:`, `fix:`, `docs:`, `refactor:`, `test:`, `ci:`, `chore:`, with `!` or a
`BREAKING CHANGE:` footer for a break. `.githooks/commit-msg` — wired up by
`composer install` — rejects anything else locally, because the prefix is an
input to the version number rather than a style preference. For a `feat` it also
checks that the commit carries a todo item, or a `Todo: none` line in the message
([[operations/contributing]]); that check never touches the version. Before
either check the hook runs `bin/tamper-guard`, which refuses a commit that
weakens a quality gate unless the message carries a `Gate-Change: <reason>`
line ([[operations/contributing]]).

## release-please owns the version and the changelog

Every push to `main` runs `release-please.yml`, which keeps a release pull request
open carrying the next version and the generated `CHANGELOG.md` entry. Merging it
tags the release.

- `feat:` bumps the minor, `fix:` the patch, a breaking change the major.
- Below 1.0.0 a breaking change bumps the minor instead.

`CHANGELOG.md` is generated. Editing it by hand loses the edit on the next run —
change the commit history's future, not the output ([[operations/contributing]]).

## SBOM per release

`composer sbom` writes a CycloneDX `sbom.json` from the lock file, uploaded as a
release asset, so a consumer can answer "does this ship the vulnerable version of
X" without cloning the repo.

The workflow topology around this is [[operations/ci]].
