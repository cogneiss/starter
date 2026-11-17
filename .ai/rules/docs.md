---
paths:
    - "wiki/**"
    - ".ai/**"
    - "*.md"
---

# Documentation

## `AGENTS.md` is generated

It is rendered from `.ai/guidelines/*.blade.php` by `php artisan boost:install --guidelines --skills`, and `CLAUDE.md` is the one line `@AGENTS.md`. Edit the Blade source and regenerate; a hand-edit is overwritten by the next `composer update` and fails `tests/Feature/Docs/GuidelinesAreCurrentTest.php` until then. Nested `AGENTS.md` files are generated too, from `.ai/rules/index.md`, by `php artisan agents:context`; run it after changing the index.

## Wiki pages are superseded, never deleted

A page that no longer applies gets `status: superseded`, and the page replacing it lists it in `supersedes`. Deleting loses the reasoning and the record of what was tried.

## Every wiki claim names the file that proves it

Cite the file in the prose and list it in `code_refs`. `code_refs` entries are file paths, never directories — a page anchored to a directory goes stale on every unrelated change in it, and a gate that goes red for unrelated reasons gets switched off. If you are unsure how something behaves, write that it is not documented rather than guessing.

## Load the wiki by `code_refs`, not by default

Do not load the wiki up front. Open a page when the task touches a file in that page's `code_refs`. After a change, reread only the pages whose `code_refs` include a changed file. No command lists them for uncommitted edits (`rg -l '<path>' wiki`); once committed, `php artisan wiki:audit` lists them under `stale`.

## One fact, one layer

Rules are normative and terse. Skills (`.ai/skills/<name>/SKILL.md`) carry procedure — do X, then Y, in this order. The wiki carries the reasoning and the alternatives that lost. Cite across layers instead of repeating; duplicated guidance drifts, and the copy that drifts is the one being read.

## `wiki/_meta/audit.json` is generated

`php artisan wiki:audit` writes it and `.githooks/post-commit` refreshes it. It is git-ignored. Regenerate it; never hand-edit it.
