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
updated: 2026-08-31
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

## What else the provider binds

`AppServiceProvider` is where two other tenancy-shaped decisions are made once
rather than at every call site:

- `FileScanner` resolves from `config('uploads.scanner')` against the named map
  in `config('uploads.scanners')`, falling back to `NullScanner` when the name is
  unknown. A typo'd scanner name therefore degrades to the loud no-op scanner
  rather than to no binding at all ([[domains/ux-import-and-uploads]]).
- The `database` notification driver is replaced with
  `OrganizationDatabaseChannel` inside `Notification::resolved()`, so every
  stored notification carries the organization it was raised in. Laravel resolves
  that driver through the container, which is why the swap belongs here and not
  in each notification ([[domains/ux-realtime-notifications]]).
