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
updated: 2026-08-24
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
OrganizationContext::runAs($organization, function () {
    // scoped queries in here see $organization
});
```

The user-visible features built on this — switcher, members, invitations, roles,
suspension, impersonation — are in [[features/organizations]].
