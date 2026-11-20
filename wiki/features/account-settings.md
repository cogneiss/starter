---
title: Account settings
status: current
supersedes: []
code_refs:
    - app/Http/Controllers/UserProfileController.php
    - app/Http/Controllers/UserPasswordController.php
    - app/Http/Controllers/BrowserSessionController.php
    - app/Support/BrowserSession.php
    - app/Actions/DeleteOtherBrowserSessions.php
    - app/Actions/DeleteUser.php
    - app/Models/LoginHistory.php
    - app/Http/Middleware/HandleAppearance.php
    - app/Models/AiMemory.php
    - app/Actions/UpdateUser.php
    - app/Actions/UpdateUserPassword.php
    - app/Http/Requests/DeleteBrowserSessionRequest.php
    - app/Http/Requests/DeleteUserRequest.php
    - app/Http/Requests/UpdateUserLocaleRequest.php
    - app/Http/Requests/UpdateUserNotificationPreferenceRequest.php
    - app/Http/Requests/UpdateUserPasswordRequest.php
    - app/Http/Requests/UpdateUserRequest.php
updated: 2026-11-20
---

# Account settings

| Screen           | Behaviour                                                                       |
| ---------------- | ------------------------------------------------------------------------------- |
| Profile          | name and email; changing the email drops verification and requires re-verifying |
| Password         | update with current-password confirmation, throttled                            |
| Passkeys         | list, name and delete registered credentials                                    |
| Two-factor       | enable, confirm, view and regenerate recovery codes, disable                    |
| Appearance       | light, dark or system, persisted in a cookie                                    |
| Browser sessions | every browser signed in, with device and last activity; log the others out      |
| Login history    | the last ten attempts, successful and failed, kept for 90 days then pruned      |
| Delete account   | password-confirmed, permanent (`app/Actions/DeleteUser.php`)                    |

The passkeys, two-factor and password screens, and account deletion, require a
verified email (`verified` in `routes/web.php`) as well as not impersonating.
The sign-up, reset and password-change pages receive a `passwordRules` string
from `Password::defaults()` in `UserController` and `UserPasswordController`.

## Two details worth knowing before changing them

**Appearance is a cookie, not a preference row, and it is not encrypted.**
`bootstrap/app.php` excludes `appearance` and `sidebar_state` from cookie
encryption so `app/Http/Middleware/HandleAppearance.php` can read the theme on the
server and render the right one on first paint. That is what removes the flash of
the wrong theme. Encrypting them puts the flash back.

**Browser sessions only work on the database session driver.**
`app/Support/BrowserSession.php` returns `[]` from `forUser()` unless
`session.driver` is `database`, because the list is built by reading the sessions
table. On the file or cookie driver the screen is empty rather than wrong. Swapping
the session driver is a supported change ([[operations/runtime]]) — this screen is
what it costs.

**Deleting the account deletes what the assistant remembered.**
`app/Actions/DeleteUser.php` wraps the delete in a transaction and purges
`ai_memories` for that user first, in every organization. The purge is explicit
rather than a cascade or a global scope: assistant memory is keyed by
organization _and_ user ([[domains/ai-memory]]), so deleting through whichever
organization happens to be bound to the request would leave the person's notes
behind in the others. A row that outlives its subject is the kind of thing a
privacy request finds later.

Logging out the other browsers is `app/Actions/DeleteOtherBrowserSessions.php`,
behind a password confirmation.

## Writes and their requests

- **Profile.** `app/Http/Requests/UpdateUserRequest.php` requires a name and a
  lowercase, unique email (ignoring the user's own row). `lowercase` stays
  beside `unique` on purpose: `unique` compares what was typed, and the `User`
  model would then store a different, lower-cased value ([[domains/models]]).
  `app/Actions/UpdateUser.php`
  clears `email_verified_at` and sends a new verification email only when the
  email actually changed.
- **Password.** `app/Http/Requests/UpdateUserPasswordRequest.php` requires the
  current password and a confirmed new one meeting `Password::defaults()`.
  `app/Actions/UpdateUserPassword.php` only sets the column; the route adds
  `throttle:6,1`.
- **Delete account and log out other browsers.** `DeleteUserRequest` and
  `DeleteBrowserSessionRequest` each validate one field, `password`, with the
  `current_password` rule. `UserController::destroy` logs out, runs `DeleteUser`,
  then invalidates the session and regenerates the CSRF token.
- **Language.** `app/Http/Requests/UpdateUserLocaleRequest.php` accepts only a
  value in `app.supported_locales`. `UserLocaleController` saves it on the user
  and in the session, so the redirect already renders in the new language
  ([[domains/ux-i18n]]).
- **Notification preferences.** `app/Http/Requests/UpdateUserNotificationPreferenceRequest.php`
  builds one required boolean rule per notification and channel pair in
  `User::NOTIFICATION_CHANNELS` (today only `organization_invitation_notification`
  on `mail` and `database`), and `preferences()` returns the matrix in the shape
  the `notification_preferences` column stores. The screen is
  `settings/notifications` in `UserNotificationPreferenceController`.

Login history rows are written by listeners rather than by these controllers
([[domains/events-and-notifications]]). `LoginHistory` lower-cases the address
it stores, so a failed attempt typed as `Ada@Example.com` is filed under
`ada@example.com`, the value account deletion looks for
([[operations/ops-gdpr]]). `LoginHistory` has no resource adapter,
exempted with a reason in `config/conventions.php`
([[architecture/convention-guards]]).
