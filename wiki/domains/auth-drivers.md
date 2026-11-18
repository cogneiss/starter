---
title: The auth driver seam
status: superseded
supersedes: []
code_refs:
    - app/Http/Controllers/SessionController.php
    - app/Http/Controllers/SocialAuthController.php
    - app/Actions/EnabledSocialProviders.php
    - config/services.php
updated: 2026-11-19
---

# The auth driver seam

Superseded by [[features/authentication]]. The `AuthDriver` interface, its
resolver and both drivers were removed: only the password driver was ever
registered, so the seam had no second user. `SessionController` and
`SocialAuthController` now do the work directly, and
`app/Actions/EnabledSocialProviders.php` holds the provider list. The text below
is kept for the record of what existed and why.

`app/Auth/Contracts/AuthDriver.php` is a three-method interface — `key()`,
`redirect()`, `authenticate()` — and `app/Auth/AuthDriverResolver.php` hands out
the driver for a key, throwing on an unknown one rather than falling back to a
default.

Two drivers ship:

- `PasswordAuthDriver` — email and password. Its `redirect()` renders
  `session/create` with `canResetPassword` and `canRegister` (each true only
  when the `password.request` or `register` route exists), the flash `status`
  and the enabled social providers.
- `SocialAuthDriver` — Socialite, with `PROVIDERS = ['google', 'github',
'microsoft']`. `enabledProviders()` returns only providers that pass two
  checks: the `social-login-enabled` feature flag
  (`App\Enums\KnownFeatures::SocialLoginEnabled`), and a non-empty
  `services.<provider>.client_id` in `config/services.php`. Missing credentials
  mean the button is absent, not broken.

## Why a seam rather than a driver per protocol

The seam exists so SAML or OIDC can be added without touching the controllers.
SAML and OIDC themselves were left out on purpose: heavy dependencies, and
per-provider debugging that a starter kit cannot pre-empt
([[decisions/not-included]]).

Read the warning in the `AuthDriver` docblock before writing one. It records two
traps that are easy to hit and hard to notice: an SSO flow that replays a host
supplied by the request, and a driver that defaults to allowing sign-in when it
cannot decide. Both fail open, which is the wrong direction for an authentication
component.

Note the naming: `app/Auth/Contracts/` holds both `AuthDriver` and
`OrganizationResolver`, and `app/Auth/Resolvers/` holds the latter's
implementations ([[domains/organization-resolvers]]). Sign-in and tenancy share a
directory but not a mechanism.

The user-facing flows built on these are in [[features/authentication]].
