---
title: Tooling
status: current
supersedes: []
code_refs:
    - pint.json
    - rector.php
    - phpstan.neon
    - knip.json
    - composer-unused.php
    - boost.json
updated: 2026-08-25
---

# Tooling

## PHP

- **Pint** formats (`pint.json`), **Rector** upgrades and refactors
  (`rector.php`). `composer lint` runs both, applying fixes.
- **PHPStan at level max** with Larastan (`phpstan.neon`), plus its dead-code
  rules as a separate gate.
- **Dead code, unused packages, orphaned frontend files** — `composer test:dead-code`,
  `composer test:deps` (`composer-unused.php`), `composer test:knip` (`knip.json`).
  Each records its accepted findings in its own baseline with a comment saying
  why, rather than being silenced by loosening the tool
  ([[architecture/fast-blocking-gates]]).
- **Pail** tails logs readably in development.

## Frontend

Vite+ (Rolldown), with oxlint and oxfmt for lint and format, Tailwind class
sorting and import sorting. The React Compiler runs through Babel, so manual
`useMemo` and `useCallback` are not needed ([[features/interface]]).

## Agent tooling

- **Laravel Boost** — an MCP server (browser log capture at `_boost/browser-logs`)
  plus ten skill packs and the guideline files, so an agent gets version-correct
  package docs instead of guessing at an API.
- `boost.json` records which agents and skills are installed, first-party packs
  included — `ai-layer` sits in that list next to `org-access`, `resource-spine`
  and `testing-gates`. `php artisan boost:install --guidelines --skills`
  regenerates all of them ([[operations/documentation]]).
- `.ai/rules/` holds the committed, path-scoped project rules, mapped by
  `.ai/rules/index.md`. Boost's `record-rule` tool adds to it, so a decision
  reached once is inherited rather than re-derived.
- **Code knowledge graphs** answer structural questions from an index
  ([[operations/code-knowledge-graphs]]).

## Security tooling

**gitleaks** scans every pull request and the full git history on a schedule, so a
key committed once does not sit in the log unnoticed. `composer audit` and
`bun audit` fail the build on a known vulnerability in either half of the stack.

## Generators

`php artisan make:action` scaffolds an action. `php artisan app:make-resource`
scaffolds a whole resource from `stubs/resource/*.stub` — model, migration,
factory, Data class, policy, action, form request, controller, resource adapter,
Inertia page, route line, permission entry and four tests
([[domains/resources]]). `php artisan app:doctor` answers "can this machine run
the app" ([[operations/setup]]).

Every command is listed in [[operations/commands]].
