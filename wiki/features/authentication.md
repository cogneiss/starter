---
title: Authentication
status: current
supersedes: []
code_refs:
    - app/Http/Controllers/SessionController.php
    - app/Http/Controllers/UserMagicLinkController.php
    - app/Http/Controllers/UserPasskeyController.php
    - app/Http/Controllers/UserTwoFactorAuthenticationController.php
    - app/Http/Controllers/SocialAuthController.php
    - app/Http/Controllers/UserEmailVerificationController.php
    - app/Providers/FortifyServiceProvider.php
    - config/fortify.php
    - app/Actions/CreateSessionFromMagicLink.php
    - app/Actions/LinkSocialAccount.php
updated: 2026-08-24
---

# Authentication

Fortify does the protocol work, configured in `config/fortify.php` and
`app/Providers/FortifyServiceProvider.php`. The controllers here are the kit's own
screens on top of it.

| Path                  | Where it lives                                                                       |
| --------------------- | ------------------------------------------------------------------------------------ |
| Registration          | name, email, password; sends a verification email and signs the user in              |
| Password login        | `SessionController`, with "remember me" and rate limiting                            |
| Social login          | `SocialAuthController` + `app/Actions/LinkSocialAccount.php`                         |
| Magic link            | `UserMagicLinkController` + `app/Actions/CreateSessionFromMagicLink.php`             |
| Passkeys              | `UserPasskeyController` — WebAuthn through Fortify, passwordless from the login page |
| Two-factor            | `UserTwoFactorAuthenticationController` — TOTP, QR code, single-use recovery codes   |
| Password reset        | Fortify's flow, with the usual token expiry and throttling                           |
| Email verification    | `UserEmailVerificationController`, plus a `verified` guard on the dashboard          |
| Password confirmation | re-prompts before sensitive settings changes                                         |

## The rules that cut across all of them

- **Every path respects two-factor.** A magic link or a passkey hands a 2FA user
  to the challenge screen; neither is a way around it. This is the kind of gap
  that appears when each sign-in path is built separately, so it is worth
  re-checking when adding one.
- **Magic link tokens last 15 minutes, work once, and never reveal whether an
  address is registered.** The last part is why the response is identical for a
  known and an unknown email.
- **Social login links rather than duplicates.** An OAuth email matching an
  existing _verified_ account links to it; an unverified match is refused, because
  accepting it would let anyone who can receive OAuth for an address take over an
  account that never proved it owned that address.
- **An organization can require two-factor for everyone in it.** Members without
  it are held on the setup screen by the `two-factor` middleware
  ([[domains/http-layer]]).

Social login is off behind a feature flag and needs credentials before a provider
button appears at all ([[domains/feature-flags]], [[domains/auth-drivers]]).
Sign-in attempts are recorded by listeners
([[domains/events-and-notifications]]).
