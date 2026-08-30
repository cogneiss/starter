---
title: Why the organization resolves before route model binding
status: current
supersedes: []
code_refs:
    - bootstrap/app.php
    - app/Http/Middleware/ResolveOrganization.php
    - .ai/rules/bootstrap.md
updated: 2026-08-31
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

`AuthenticateSession`, `EnsureUserIsActive`, `HandleAppearance`, `SetLocale`,
`HonorDoNotTrack`, `ResolveOrganization`, `HandleBrand`,
`HandleInertiaRequests`, `AddLinkHeadersForPreloadedAssets`.

Two of those are placed rather than appended. `SetLocale` sits with the other
preference middleware and before anything that produces text, so a validation
message is written in the language the request asked for
([[domains/ux-i18n]]). `HandleBrand` sits _after_ `ResolveOrganization`,
because the palette it injects is the current organization's and there is no
palette to derive before one is bound ([[domains/ux-branding]]).

Reordering or removing the priority-list entry is not safe, and there is no
version of that change that only breaks a little. `.ai/rules/bootstrap.md` says
the same thing in one line to anyone editing the file.

`bootstrap/app.php` also names the route middleware aliases — `organization`
(`RequireOrganization`), `not-impersonating` (`ForbiddenDuringImpersonation`),
`two-factor` (`EnsureTwoFactorEnabled`) and `onboarded`
(`RedirectIfNotOnboarded`, [[domains/ux-onboarding]]) — and leaves the
`appearance` and `sidebar_state` cookies out of encryption so the server can
read them before hydration.

Two more things hang off this file. `withBroadcasting()` mounts
`routes/channels.php` behind `['web', 'auth']`, so a channel authorization
request runs the same stack a page does and the permission gates read from a
resolved organization rather than from nothing
([[domains/ux-realtime-notifications]]). And `withExceptions()` now hands every
response to `UserFriendlyExceptionRegistrar`, which is where a recognised
exception is turned into a sentence a person can act on.

See [[domains/organization-resolvers]] for what `ResolveOrganization` delegates
to, and [[architecture/fail-closed-scoping]] for what happens when nothing is
bound.
