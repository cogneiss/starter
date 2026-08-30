---
title: Multi-tenancy
status: current
supersedes: []
code_refs:
    - app/Support/OrganizationContext.php
    - app/Concerns/BelongsToOrganization.php
    - app/Contracts/OrganizationAware.php
    - app/Queue/Middleware/WithOrganizationContext.php
    - app/Exceptions/OrganizationContextMissing.php
    - app/Http/Middleware/RequireOrganization.php
    - config/organizations.php
updated: 2026-08-31
---

# Multi-tenancy

Every user belongs to at least one organization, and everything they create lives
inside one. Signing up alone creates a personal organization, which is why the
same model suits a B2C product.

## The parts

| File                                               | Job                                                                         |
| -------------------------------------------------- | --------------------------------------------------------------------------- |
| `app/Support/OrganizationContext.php`              | singleton holding the current organization; `runAs()` binds one per closure |
| `app/Concerns/BelongsToOrganization.php`           | global scope plus automatic `organization_id` filling                       |
| `app/Exceptions/OrganizationContextMissing.php`    | thrown when a scoped query runs with nothing bound                          |
| `app/Contracts/OrganizationAware.php`              | marker for jobs that touch scoped models                                    |
| `app/Queue/Middleware/WithOrganizationContext.php` | rebinds the organization around a queued job                                |
| `app/Http/Middleware/RequireOrganization.php`      | the `organization` route alias: a request without one goes no further       |

Scoped models are filtered everywhere the scope applies, relations included.
Escaping it is possible and deliberate: `withoutOrganizationScope()`, with a
comment saying why.

## Queued jobs

A job that touches scoped models implements `OrganizationAware` and returns
`WithOrganizationContext` from `middleware()`. Without that, the job runs under
whatever organization the previous job on that worker left bound — which is not a
missing-context error, it is the wrong organization's data, and it looks like it
worked.

## Configuration

`config/organizations.php`:

- `strict` — `ORGANIZATIONS_STRICT`, default `true`. See
  [[architecture/fail-closed-scoping]].
- `resolver` — `ORGANIZATIONS_RESOLVER`, default `session`, one of `session`,
  `subdomain`, `single`. See [[domains/organization-resolvers]].

## In tests

Bind explicitly:

```php
resolve(OrganizationContext::class)->runAs($organization, function (): void {
    // scoped queries in here see $organization
});
```

`OrganizationContext::run($organization, $callback)` is the same thing without
resolving the singleton first, which is what a job or a command reaches for.

## Where the scope is the security control

The UX layer added surfaces that return many records at once — a search
endpoint, a list, a CSV export, a notification count — and every one of them
puts the organization in the `where` clause rather than filtering afterwards. A
count cannot be filtered after the fact, and an export that fetches first and
checks second has already read the rows.
`tests/Feature/CrossOrgLeakTest.php` asks each of those surfaces for a foreign
record and requires a 404: **absent, not forbidden**, because a 403 confirms the
record exists.

The user-visible features built on this — switcher, members, invitations, roles,
suspension, impersonation — are in [[features/organizations]].
