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
    - app/Actions/CreateUser.php
    - app/Actions/CreateUserEmailResetNotification.php
    - app/Actions/CreateUserEmailVerificationNotification.php
    - app/Actions/CreateUserMagicLinkNotification.php
    - app/Actions/CreateUserPassword.php
    - app/Http/Controllers/UserEmailResetNotificationController.php
    - app/Http/Controllers/UserEmailVerificationNotificationController.php
    - app/Http/Requests/CreateUserEmailResetNotificationRequest.php
    - app/Http/Requests/CreateUserMagicLinkRequest.php
    - app/Http/Requests/CreateUserPasswordRequest.php
    - app/Http/Requests/ShowUserTwoFactorAuthenticationRequest.php
    - app/Http/Requests/UpdateEmailVerificationRequest.php
updated: 2026-11-18
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
  account that never proved it owned that address. A new identity is also
  refused when its email is empty or the provider does not vouch for it: GitHub
  returns only a verified primary address, Google must say `email_verified`, and
  any other provider is refused (`app/Actions/LinkSocialAccount.php`).
- **Passkey sign-in is throttled like password login.** The `passkeys` limiter in
  `FortifyServiceProvider` allows 10 attempts a minute per credential id and IP,
  falling back to the session when no credential id is sent.
- **An organization can require two-factor for everyone in it.** Members without
  it are held on the setup screen by the `two-factor` middleware
  ([[domains/http-layer]]).

Social login is off behind a feature flag and needs credentials before a provider
button appears at all. `app/Actions/EnabledSocialProviders.php` is the one place
that decides which of `google` and `github` are live: the
`social-login-enabled` flag must be on and `services.<provider>.client_id` must be
non-empty. `SessionController` passes that list to the login page and
`SocialAuthController` returns a 404 for any provider outside it
([[domains/feature-flags]]).

## Sign-up, reset, magic link and verification mechanics

Each of these is a thin controller over an action in `app/Actions`.

- **Sign-up.** `app/Actions/CreateUser.php` runs in one transaction: it creates
  the user, creates a personal organization named after the user through
  `CreateOrganization`, and fires `Registered`. `UserController::store` then logs
  the user in and regenerates the session.
- **Password reset request.** `UserEmailResetNotificationController::store`
  passes only the email to `app/Actions/CreateUserEmailResetNotification.php`,
  which calls `Password::sendResetLink`. The reply is the same sentence whether
  or not the account exists. `CreateUserEmailResetNotificationRequest` checks
  only `required` and `email`; it does not use `ValidEmail`.
- **Password reset.** `app/Actions/CreateUserPassword.php` wraps
  `Password::reset` in a transaction, sets the new password, rotates
  `remember_token` and fires `PasswordReset`. `CreateUserPasswordRequest`
  requires `token`, `email` and a confirmed password meeting `Password::defaults()`.
  `UserPasswordController::store` turns any status other than a successful reset
  into a validation error on `email`.
- **Magic link.** `app/Actions/CreateUserMagicLinkNotification.php` returns
  silently for an unknown email. Otherwise it stores a 40-character random token
  in the cache under `magic-link:<token>` with the user id, for
  `auth.magic_link.expire` minutes, and notifies the user. Tokens live in the
  cache, so production needs a shared, persistent `CACHE_STORE`.
  `CreateUserMagicLinkRequest` validates the email with `ValidEmail`. In
  `routes/web.php` both the request and the link itself are `throttle:6,1`, and
  the request also carries `friction`.
- **Verification email.** `UserEmailVerificationNotificationController` redirects
  an already verified user to the dashboard on both `create` and `store`;
  otherwise `store` calls `app/Actions/CreateUserEmailVerificationNotification.php`
  and flashes `verification-link-sent`. The route is `throttle:6,1`.
- **Two-factor screen.** `ShowUserTwoFactorAuthenticationRequest` has no rules.
  It exists to mix in Fortify's `InteractsWithTwoFactorState` for
  `UserTwoFactorAuthenticationController::show`.

`app/Http/Requests/UpdateEmailVerificationRequest.php` is an empty subclass of
Laravel's `EmailVerificationRequest`, but nothing in `app` or `routes` references
it: `UserEmailVerificationController::update` type-hints the framework class.

## One login flow, no driver setting

Login is always email and password plus whichever social providers are enabled.
`SessionController` checks the credentials itself and dispatches
`Illuminate\Auth\Events\Failed` on a miss, which `RecordFailedLogin` turns into
a login-history row without the password. There is no `AUTH_DRIVER` setting any
more: it only ever accepted `password`, and the pluggable driver seam it fronted
was removed. The superseded page `wiki/domains/auth-drivers.md` keeps the reasoning.

Anyone adding SAML or OIDC later must follow the rules that lived on the old
interface; the first two are now a docblock on `SocialAuthController`:

- Check the assertion against the request host before trusting it. An assertion
  minted for one host and replayed at another signs the attacker into a different
  organization.
- Deny by default. Do not provision an account for an unknown identity unless the
  organization has explicitly opted in.
- Fail closed when the configured flow is unknown or cannot decide.

Signing out ends an active impersonation first: `SessionController::destroy`
calls `StopImpersonation` before logging the guard out.

Sign-in attempts are recorded by listeners
([[domains/events-and-notifications]]).
