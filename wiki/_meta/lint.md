# wiki:lint

`composer test:wiki` runs `php artisan wiki:lint`. It blocks. Five rules.

Frontmatter is required on every page, all five keys, or nothing else is checked:

```yaml
---
title: Two-gate authorization
status: current # or superseded
supersedes: [] # slugs this page replaces
code_refs:
    - app/Policies/OrganizationPolicy.php
updated: 2026-08-24
---
```

| Rule        | Fails when                                                                                            |
| ----------- | ----------------------------------------------------------------------------------------------------- |
| frontmatter | A page is missing one of the five keys — nothing else on that page is checked                         |
| 1           | A `code_refs` entry does not exist, or is a directory                                                 |
| 2           | A `[[wikilink]]` points at a page that does not exist                                                 |
| 3           | A page links to a `superseded` page without also linking the page that supersedes it, or nothing does |
| 5           | A file in `code_refs` has a commit newer than the page's `updated:` date                              |

## Rule 1 — paths, not directories

`code_refs` are file paths. A directory ref matches every commit that touches
anything inside it, so rule 5 would fire on unrelated work, and a blocking gate
that goes red for unrelated reasons gets switched off. Name the files the page
actually explains.

## Rule 5 — the one to be careful with

The comparison is at date granularity, so a commit on the same day as `updated:`
is not drift.

**The failure mode to watch is bumping `updated:` on its own.** It clears the gate
and leaves the page wrong, which is worse than the page being flagged. The
rule's own fix line says so:

> Run /document to reread the code and rewrite the page. Bumping `updated:` on its
> own clears this gate and hides the drift.

Reread the file the ref names, fix what the code actually says now, then set
`updated:` to today.

## Pages are superseded, never deleted

Set `status: superseded`, and list the replacement slug in the replacement page's
`supersedes:`. A link into an old page then still lands somewhere true. Deleting
the page breaks every link into it, including the ones in other people's notes.

## Writing rules

- Every claim names the file that proves it. No claim without a file.
- Uncertain means "not documented". Never guess at behaviour.
- Generated files are not documented by hand — see
  [[operations/documentation]].
