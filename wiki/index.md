---
title: Wiki index
status: current
supersedes: []
code_refs:
    - .ai/rules/index.md
updated: 2026-09-01
---

# Wiki index

Long-form documentation for this starter kit, written for whoever reads it next —
usually an agent that has never opened these files.

Three documentation layers exist, and they do different jobs. Do not merge them:

| Layer             | Lives in                                          | Job                                                                      |
| ----------------- | ------------------------------------------------- | ------------------------------------------------------------------------ |
| Path-scoped rules | `.ai/rules/*.md`                                  | Short, imperative, loaded automatically when a matching file is touched. |
| Wiki pages        | `wiki/**`                                         | The long version: how a thing works, why it is shaped that way.          |
| Skill packs       | `.ai/skills/*/`, published to `.claude/skills/*/` | Decision procedures for one domain, loaded on demand.                    |

A rule says "do this". A wiki page says "here is the whole thing, and here is the
file that proves it". Every claim on a page names a file. Where a page is unsure,
it says the behaviour is not documented rather than guessing.

## Which layer does a new piece of guidance belong in

Answer in this order, and put it in exactly one place:

1. **Is it a constraint that must hold every time a matching file is touched?**
   A rule. `.ai/rules/`, terse and imperative, with a `paths:` glob and a row in
   `.ai/rules/index.md`. Rules are loaded automatically, so they stay short —
   anything long enough to skim is too long to be a rule.
2. **Is it a procedure for a task — "when you are doing X, do Y, in this order"?**
   A skill. `.ai/skills/<name>/SKILL.md`, authored here and published into the
   agent directories by `php artisan boost:install --skills`. Five ship:
   `resource-spine`, `org-access`, `testing-gates`, `ai-layer`, `ux-kit`. A skill is read while
   working, so it names exact commands and file paths and states the failure it
   prevents. About 150 lines is the ceiling.
3. **Is it the reasoning — why the thing is shaped this way, what was rejected?**
   A wiki page. It is the only layer with room for the alternatives that lost.

The layers cite each other rather than repeat: a rule can say "see the wiki
page", and every skill ends with the pages behind it. Duplicated guidance drifts,
and the copy that drifts is always the one being read.

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

## The UX layer

- [[domains/ux-search-and-palette]]
- [[domains/ux-list-kit]]
- [[domains/ux-filters-and-saved-searches]]
- [[domains/ux-primitives]]
- [[domains/ux-motion-and-a11y]]
- [[domains/ux-branding]]
- [[domains/ux-realtime-notifications]]
- [[domains/ux-forms-precognition]]
- [[domains/ux-onboarding]]
- [[domains/ux-import-and-uploads]]
- [[domains/ux-i18n]]

## The AI layer

- [[domains/ai-layer-overview]]
- [[domains/ai-agents-and-tools]]
- [[domains/ai-injection-defense]]
- [[domains/ai-confirm-tokens]]
- [[domains/ai-blocks]]
- [[domains/ai-metering-and-quotas]]
- [[domains/ai-retrieval]]
- [[domains/ai-memory]]
- [[domains/ai-mcp-server]]
- [[domains/ai-evals]]

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
- [[operations/ops-api-tokens]]
- [[operations/ops-read-api]]
- [[operations/ops-usage-and-limits]]
- [[operations/ops-audit-log]]
- [[operations/ops-gdpr]]
- [[operations/ops-health]]
- [[operations/ops-error-reporting]]
- [[operations/ops-analytics]]
- [[operations/ops-webhooks]]
- [[operations/ops-csp]]
- [[operations/ops-admin-area]]

## Decisions — what was built, and what was left out

- [[decisions/org-access]]
- [[decisions/resource-spine]]
- [[decisions/ci-quality]]
- [[decisions/agent-dx]]
- [[decisions/not-included]]
