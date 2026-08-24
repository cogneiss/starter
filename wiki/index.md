---
title: Wiki index
status: current
supersedes: []
code_refs:
    - .ai/rules/index.md
updated: 2026-08-24
---

# Wiki index

Long-form documentation for this starter kit, written for whoever reads it next —
usually an agent that has never opened these files.

Three documentation layers exist, and they do different jobs. Do not merge them:

| Layer             | Lives in            | Job                                                                      |
| ----------------- | ------------------- | ------------------------------------------------------------------------ |
| Path-scoped rules | `.ai/rules/*.md`    | Short, imperative, loaded automatically when a matching file is touched. |
| Wiki pages        | `wiki/**`           | The long version: how a thing works, why it is shaped that way.          |
| Skill packs       | `.claude/skills/*/` | Decision procedures for one domain, loaded on demand.                    |

A rule says "do this". A wiki page says "here is the whole thing, and here is the
file that proves it". Every claim on a page names a file. Where a page is unsure,
it says the behaviour is not documented rather than guessing.

Pages carry frontmatter, and `php artisan wiki:lint` fails the build when a page
rots. The rules it enforces are written down in [`_meta/lint.md`](_meta/lint.md).
See [[operations/documentation]] for the loop that keeps pages current.

## Architecture — why the shape is the shape

- [[architecture/actions-and-controllers]]
- [[architecture/fail-closed-scoping]]
- [[architecture/resolve-before-routing]]
- [[architecture/six-method-spine]]
- [[architecture/two-gate-authorization]]
- [[architecture/fast-blocking-gates]]
- [[architecture/graph-before-grep]]
- [[architecture/type-safety]]
- [[architecture/convention-guards]]
- [[architecture/better-defaults]]

## Domains — how each part works

- [[domains/multi-tenancy]]
- [[domains/organization-resolvers]]
- [[domains/auth-drivers]]
- [[domains/authorization]]
- [[domains/resources]]
- [[domains/feature-flags]]
- [[domains/models]]
- [[domains/data-objects]]
- [[domains/http-layer]]
- [[domains/events-and-notifications]]
- [[domains/console-commands]]

## Features — what a user touches

- [[features/authentication]]
- [[features/account-settings]]
- [[features/organizations]]
- [[features/pages]]
- [[features/interface]]

## Operations — running, testing, shipping

- [[operations/setup]]
- [[operations/contributing]]
- [[operations/ci]]
- [[operations/testing]]
- [[operations/tooling]]
- [[operations/commands]]
- [[operations/releases]]
- [[operations/code-knowledge-graphs]]
- [[operations/runtime]]
- [[operations/documentation]]

## Decisions — what was built, and what was left out

- [[decisions/org-access]]
- [[decisions/resource-spine]]
- [[decisions/ci-quality]]
- [[decisions/agent-dx]]
- [[decisions/not-included]]
