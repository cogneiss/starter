---
title: Authentication
status: current
supersedes:
    - domains/auth-drivers
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
    - app/Actions/EnabledSocialProviders.php
    - app/Http/Requests/CreateSessionRequest.php
updated: 2026-11-17
---

# Authentication

Fortify does the protocol work for two-factor authentication and passkeys only.
`config/fortify.php` lists just the values that differ from Fortify's own
defaults: lowercased usernames, `/dashboard` as the home path, the named
limiters, and a features list holding two-factor (with `confirm` and
`confirmPassword`) and passkeys (with `confirmPassword`). Guard, password broker,
`email` as the username field, views and route prefix come from the package
defaults in `vendor/laravel/fortify/config/fortify.php`, as do the passkey
relying-party, origin, handle-secret and timeout settings. The `login`,
`two-factor` and `passkeys` limiters are defined in
`app/Providers/FortifyServiceProvider.php`. Registration, password reset, email
verification, profile and password updates are the kit's own routes and
controllers in `routes/web.php`, not Fortify features. The controllers here are
the kit's own screens on top of it.

| Path                  | Where it lives                                                                      |
| --------------------- | ----------------------------------------------------------------------------------- |
| Registration          | name, email, password; sends a verification email and signs the user in             |
| Password login        | `SessionController`, with "remember me" and rate limiting                           |
| Social login          | `SocialAuthController` + `app/Actions/LinkSocialAccount.php`                        |
| Magic link            | `UserMagicLinkController` + `app/Actions/CreateSessionFromMagicLink.php`            |
| Passkeys              | `UserPasskeyController` — WebAuthn through Fortify, passwordless, 10 tries a minute |
| Two-factor            | `UserTwoFactorAuthenticationController` — TOTP, QR code, single-use recovery codes  |
| Password reset        | `UserEmailResetNotificationController` and `UserPasswordController`, not Fortify    |
| Email verification    | `UserEmailVerificationController`, plus a `verified` guard on the dashboard         |
| Password confirmation | re-prompts before sensitive settings changes                                        |

## The rules that cut across all of them

- **Every path respects two-factor.** A magic link, a passkey or a social
  callback hands a 2FA user to the challenge screen; none is a way around it. This is the kind of gap
  that appears when each sign-in path is built separately, so it is worth
  re-checking when adding one.
- **Magic link tokens last 15 minutes, work once, and never reveal whether an
  address is registered.** The last part is why the response is identical for a
  known and an unknown email.
- **The magic-link request form carries the same bot friction as every other
  unauthenticated form** — a honeypot field and a minimum dwell time, invisible
  to a person filling it in ([[operations/ops-csp]]).
- **Social login links rather than duplicates.** An OAuth email matching an
  existing _verified_ account links to it; an unverified match is refused, because
  accepting it would let anyone who can receive OAuth for an address take over an
  account that never proved it owned that address.
- **Passkey sign-in is throttled like password login.** The `passkeys` limiter in
  `FortifyServiceProvider` allows 10 attempts a minute per credential id and IP,
  falling back to the session when no credential id is sent.
- **An organization can require two-factor for everyone in it.** Members without
  it are held on the setup screen by the `two-factor` middleware
  ([[domains/http-layer]]).

Social login is off behind a feature flag and needs credentials before a provider
button appears at all. `app/Actions/EnabledSocialProviders.php` is the one place
that decides which of `google`, `github` and `microsoft` are live: the
`social-login-enabled` flag must be on and `services.<provider>.client_id` must be
non-empty. `SessionController` passes that list to the login page and
`SocialAuthController` returns a 404 for any provider outside it
([[domains/feature-flags]]).

## One login flow, no driver setting

Login is always email and password plus whichever social providers are enabled.
`SessionController` checks the credentials itself and dispatches
`Illuminate\Auth\Events\Failed` on a miss, which `RecordFailedLogin` turns into
a login-history row without the password. There is no `AUTH_DRIVER` setting any
more: it only ever accepted `password`, and the pluggable driver seam it fronted
was removed. The superseded page `wiki/domains/auth-drivers.md` keeps the reasoning.

Anyone adding SAML or OIDC later must follow the rules that lived on the old
interface, which are now a docblock on `SocialAuthController`:

- Check the assertion against the request host before trusting it. An assertion
  minted for one host and replayed at another signs the attacker into a different
  organization.
- Deny by default. Do not provision an account for an unknown identity unless the
  organization has explicitly opted in.
- Fail closed when the configured flow is unknown or cannot decide.

Sign-in attempts are recorded by listeners
([[domains/events-and-notifications]]).
