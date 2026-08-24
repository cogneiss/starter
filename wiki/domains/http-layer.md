---
title: The HTTP layer
status: current
supersedes: []
code_refs:
    - routes/web.php
    - app/Http/Controllers/UserController.php
    - app/Http/Requests/CreateUserRequest.php
    - app/Rules/ValidEmail.php
    - app/Http/Middleware/HandleInertiaRequests.php
    - app/Http/Middleware/EnsureUserIsActive.php
    - app/Http/Middleware/EnsureTwoFactorEnabled.php
    - app/Http/Middleware/ForbiddenDuringImpersonation.php
    - app/Http/Middleware/HandleAppearance.php
    - app/Http/Middleware/HonorDoNotTrack.php
updated: 2026-08-24
---

# The HTTP layer

Eighteen controllers, sixteen form requests, eight middleware, one validation
rule.

## Routes

`routes/web.php`. `/` renders the `welcome` page as the `home` route. The
dashboard sits behind four middleware — `auth`, `verified`, `organization`,
`two-factor` — which is the standard stack for anything inside an organization.

Organization `create` and `store` are deliberately _outside_ the `organization`
middleware: a user with no organization has to be able to make one, and requiring
an organization to create an organization is a lockout. Settings, members and
invitations are all inside it.

## Form requests

Every write goes through a form request in `app/Http/Requests`, named after the
action it validates (`CreateUserRequest`, `UpdateOrganizationRequest`, and so on).
Email fields use `app/Rules/ValidEmail.php` rather than each request inventing its
own rule.

Authorization does not live here; it lives in the policies
([[architecture/two-gate-authorization]]).

## Middleware

| Middleware                     | Does                                                                      |
| ------------------------------ | ------------------------------------------------------------------------- |
| `HandleInertiaRequests`        | shares the props every page gets                                          |
| `ResolveOrganization`          | binds the current organization ([[domains/organization-resolvers]])       |
| `RequireOrganization`          | alias `organization` — no organization, no further                        |
| `EnsureUserIsActive`           | a suspended or deactivated user is signed out of the request              |
| `EnsureTwoFactorEnabled`       | alias `two-factor` — holds a member on setup when their org requires it   |
| `ForbiddenDuringImpersonation` | alias `not-impersonating` — blocks destructive routes while impersonating |
| `HandleAppearance`             | reads the `appearance` cookie so the server renders the right theme       |
| `HonorDoNotTrack`              | respects the `DNT` header                                                 |

The order they run in, and why `ResolveOrganization` is special, is in
[[architecture/resolve-before-routing]]. Controllers stay cruddy —
[[architecture/actions-and-controllers]].
