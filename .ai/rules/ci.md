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

## Coverage and phpstan need explicit memory and ini on Herd

Herd's PHP ignores PHPRC and PHP_INI_SCAN_DIR for memory_limit (always 128M), but coverage only works when PHPRC points at an ini that loads Xdebug. So `composer test:unit`, `composer test` and `php artisan app:doctor` all need that PHPRC exported; a bare `php -d memory_limit=2G vendor/bin/phpstan analyse` still crashes because parallel workers do not inherit -d. Pass `--memory-limit=2G` to phpstan itself, which `composer test:types` already does.

Pest TIA is on (`pest()->tia()->locally()`). `Pest\Support\Coverage::report()` merges the TIA baseline (`~/.pest/tia/<project>/coverage.bin.gz`) into the terminal report, so after you move lines in a file the baseline's old line map shows up as uncovered lines that are blank or docblock in the file on disk. Clover from the same run is written by PHPUnit directly, skips the merge and reports the truth — when the two disagree, believe clover and reseed with `composer test:tia-seed`. It exits 2 on the known `widgets.create` permission-ordering failures, which is expected. Never delete the TIA directory by hand; `--fresh` rebuilds it.
