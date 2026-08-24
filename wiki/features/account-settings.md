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
updated: 2026-08-24
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

Logging out the other browsers is `app/Actions/DeleteOtherBrowserSessions.php`,
behind a password confirmation.

Login history rows are written by listeners rather than by these controllers
([[domains/events-and-notifications]]). `LoginHistory` has no resource adapter,
exempted with a reason in `config/conventions.php`
([[architecture/convention-guards]]).
