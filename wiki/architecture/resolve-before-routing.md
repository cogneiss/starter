---
title: Why the organization resolves before route model binding
status: current
supersedes: []
code_refs:
    - bootstrap/app.php
    - app/Http/Middleware/ResolveOrganization.php
    - .ai/rules/bootstrap.md
updated: 2026-08-24
---

# Why the organization resolves before route model binding

`bootstrap/app.php` prepends `ResolveOrganization` to the middleware priority
list, before `SubstituteBindings`:

```php
->prependToPriorityList(before: SubstituteBindings::class, prepend: ResolveOrganization::class)
```

## The reason

Route model binding runs a query. For a scoped model that query goes through the
global scope, which reads the bound organization. Substitute bindings first and
every `{organization}`, `{member}` or `{invitation}` in a route throws
`OrganizationContextMissing` before the controller is ever reached — a 500 on
every scoped route, with a stack trace that points at the framework rather than
at the ordering mistake.

Membership in the `web` group is not enough. The `web` group runs in order, but
the priority list is what decides where the framework's own middleware sits
relative to yours, and `SubstituteBindings` comes from the framework.

## What is safe to change

Appending middleware to the `web` group is routine. The current order in
`bootstrap/app.php` is:

`AuthenticateSession`, `EnsureUserIsActive`, `HandleAppearance`,
`HonorDoNotTrack`, `ResolveOrganization`, `HandleInertiaRequests`,
`AddLinkHeadersForPreloadedAssets`.

Reordering or removing the priority-list entry is not safe, and there is no
version of that change that only breaks a little. `.ai/rules/bootstrap.md` says
the same thing in one line to anyone editing the file.

`bootstrap/app.php` also names the route middleware aliases — `organization`
(`RequireOrganization`), `not-impersonating` (`ForbiddenDuringImpersonation`) and
`two-factor` (`EnsureTwoFactorEnabled`) — and leaves the `appearance` and
`sidebar_state` cookies out of encryption so the server can read them before
hydration.

See [[domains/organization-resolvers]] for what `ResolveOrganization` delegates
to, and [[architecture/fail-closed-scoping]] for what happens when nothing is
bound.
