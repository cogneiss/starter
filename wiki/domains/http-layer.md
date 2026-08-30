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
    - app/Http/Controllers/OrganizationController.php
updated: 2026-08-31
---

# The HTTP layer

Thirty-five controllers, twenty form requests, eleven middleware, one validation
rule.

## Routes

`routes/web.php`. `/` renders the `welcome` page as the `home` route. The
dashboard sits behind four middleware — `auth`, `verified`, `organization`,
`two-factor` — which is the standard stack for anything inside an organization.

Organization `create` and `store` are deliberately _outside_ the `organization`
middleware: a user with no organization has to be able to make one, and requiring
an organization to create an organization is a lockout. Settings, members and
invitations are all inside it.

`settings/organization/ai-usage` is inside it too, and that placement is the
whole of its tenancy: `OrganizationController::aiUsage()` reads the bound
organization from `OrganizationContext`, authorizes `view` on it, and summarizes
only that organization's spend. There is no organization id in the URL, so there
is nothing to tamper with ([[domains/ai-metering-and-quotas]]).

Three more AI routes sit behind `auth`, and each is a POST that does one thing:
`ai/blocks` runs the block composer, `ai/proposals` asks a vertical for a
proposal, and `ai/confirm/{token}` spends a confirm token. The first two carry
the `organization` middleware, because an agent with no organization bound is an
agent with nothing to scope its reads to
([[architecture/fail-closed-scoping]]).

`ai/confirm/{token}` deliberately does not. The token already names the
organization it was minted in, and the write is authorized from the token's own
record rather than from whatever organization the browser happens to have
switched to since ([[domains/ai-confirm-tokens]]).

The UX layer's routes sit inside that same signed-in group, with two additions to
it: the `onboarded` alias and `HandlePrecognitiveRequests`, so every form in the
group can be validated live without opting in per route
([[domains/ux-forms-precognition]]). `search` is the palette's endpoint
([[domains/ux-search-and-palette]]); `settings/saved-searches` is a full
resource ([[domains/ux-filters-and-saved-searches]]);
`settings/imports/*` covers templates, uploads, batches and retries
([[domains/ux-import-and-uploads]]); and `settings/locale` plus
`settings/notifications` persist the two preferences that were previously only
in the browser.

## Shared props

`HandleInertiaRequests` is the one place a prop reaches every page, so what it
adds is a standing cost. The UX layer added five: `locale`, `supportedLocales`
and `translations` ([[domains/ux-i18n]]), and `unreadNotifications` plus
`recentNotifications` ([[domains/ux-realtime-notifications]]).

Two details in there are load-bearing. `translations()` reads the `ui` file
through `Lang::getLoader()->load()` rather than `Lang::get()`, because `get()`
falls back to English for a missing key and quiet English on a French screen is
worse than a visible missing string. And the unread count is a query narrowed to
the bound organization, not a filter over the user's notifications — a count
cannot be filtered after the fact ([[domains/multi-tenancy]]). Only the five most
recent rows are shared; the rest are a request away.

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
| `SetLocale`                    | picks the request's language ([[domains/ux-i18n]])                        |
| `HandleBrand`                  | shares the organization's palette ([[domains/ux-branding]])               |
| `RedirectIfNotOnboarded`       | alias `onboarded` — holds a new member on the checklist                   |

The order they run in, and why `ResolveOrganization` is special, is in
[[architecture/resolve-before-routing]]. Controllers stay cruddy —
[[architecture/actions-and-controllers]].
