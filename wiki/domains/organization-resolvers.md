---
title: Organization resolvers
status: current
supersedes: []
code_refs:
    - app/Auth/Contracts/OrganizationResolver.php
    - app/Auth/Resolvers/SessionOrganizationResolver.php
    - app/Auth/Resolvers/SingleOrganizationResolver.php
    - app/Auth/Resolvers/SubdomainOrganizationResolver.php
    - app/Http/Middleware/ResolveOrganization.php
    - app/Providers/AppServiceProvider.php
    - config/organizations.php
updated: 2026-08-24
---

# Organization resolvers

`app/Http/Middleware/ResolveOrganization.php` asks one resolver which
organization this request belongs to, then binds it into
`OrganizationContext`. Which resolver is bound comes from
`organizations.resolver` in `config/organizations.php`
(`ORGANIZATIONS_RESOLVER`), wired up in `app/Providers/AppServiceProvider.php`.

Three implement `app/Auth/Contracts/OrganizationResolver.php`:

| Key         | File                                | Picks                                                      |
| ----------- | ----------------------------------- | ---------------------------------------------------------- |
| `session`   | `SessionOrganizationResolver.php`   | the user's `currentOrganization` (default)                 |
| `single`    | `SingleOrganizationResolver.php`    | the user's oldest organization                             |
| `subdomain` | `SubdomainOrganizationResolver.php` | the organization whose `slug` matches the first host label |

All three verify membership with `belongsToOrganization` before binding. That
check is not a formality: a session value, a URL host and a stale row are all
attacker-influenced or stale-able inputs, and a resolver that trusts one of them
hands over another organization's data with the scope working exactly as designed.

`single` exists for applications that will only ever have one organization. It
keeps the scoping machinery in place, so growing into real multi-tenancy later is
a config change rather than a migration of every query.

`subdomain` is off by default. Turning it on means deciding what the apex domain
does — a host classifier for "apex vs organization root" was considered and left
out precisely because it only makes sense once subdomains are on
([[decisions/not-included]]).

Ordering matters: the middleware runs before route model binding, for the reason
in [[architecture/resolve-before-routing]]. The sign-in side of `app/Auth` is
[[domains/auth-drivers]].
