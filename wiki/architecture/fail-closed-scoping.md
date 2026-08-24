---
title: Why organization scoping is fail-closed
status: current
supersedes: []
code_refs:
    - app/Concerns/BelongsToOrganization.php
    - app/Support/OrganizationContext.php
    - app/Exceptions/OrganizationContextMissing.php
    - config/organizations.php
    - .ai/rules/app.md
updated: 2026-08-24
---

# Why organization scoping is fail-closed

With no organization bound, a query against a scoped model throws
`App\Exceptions\OrganizationContextMissing`. It does not return every row, and it
does not return none.

## The failure mode this prevents

A tenancy bug that returns nothing is a support ticket. A tenancy bug that
returns another organization's rows is a data breach that renders correctly, gets
screenshotted, and is discovered by a customer. Silence is the expensive failure,
so `app/Concerns/BelongsToOrganization.php` refuses to build a query it cannot
scope.

The trait does two things: adds the global scope that filters by
`organization_id`, and fills `organization_id` on create. Both read from
`App\Support\OrganizationContext`, the singleton holding the current
organization.

## The escape hatches, and why they are named

- `withoutOrganizationScope()` — deliberate, greppable, and per the rule in
  `.ai/rules/app.md` it carries a comment saying why. A hand-written
  `where('organization_id', ...)` is not an escape hatch, it is a second
  implementation of the scope that will disagree with the first one.
- `runAs()` on the `OrganizationContext` singleton — binds an organization for the duration of a
  closure. This is how tests and queued jobs establish a tenant. It restores the
  previous context afterwards, so nesting is safe.

## The one switch, and its only use

`config/organizations.php` reads `ORGANIZATIONS_STRICT`, defaulting to `true`.
Set to `false`, an unbound scoped query returns an empty result instead of
throwing. The one situation that justifies it is migrating an existing database
that has rows predating the column. It is not a way to make a failing test pass —
a test that hits this needs `runAs()`.

The vocabulary is fixed too: user-facing text says "organization", never
"tenant".

Further reading: [[domains/multi-tenancy]] for the moving parts,
[[architecture/resolve-before-routing]] for why the binding happens where it
does.
