---
title: Events, listeners and notifications
status: current
supersedes: []
code_refs:
    - app/Events/OrganizationCreated.php
    - app/Listeners/RecordSuccessfulLogin.php
    - app/Listeners/RecordFailedLogin.php
    - app/Notifications/OrganizationInvitationNotification.php
    - app/Notifications/UserMagicLink.php
    - app/Actions/SeedOrganizationRoles.php
updated: 2026-08-24
---

# Events, listeners and notifications

Small surface on purpose. One first-party event, two listeners, two
notifications.

## Event

`app/Events/OrganizationCreated.php` fires when an organization is created.
Cloning the role templates into the new organization is
`app/Actions/SeedOrganizationRoles.php` — an action, so it can also be called
directly from a seeder or a command without dispatching anything
([[domains/authorization]]).

## Listeners

`app/Listeners/RecordSuccessfulLogin.php` and
`app/Listeners/RecordFailedLogin.php` listen to the framework's own
authentication events and write `LoginHistory` rows. They are listeners rather
than code in the controller because Fortify owns those flows: password, magic
link, passkey and social sign-in all end in a framework event, and a listener
records all of them once instead of four controllers each remembering to.

## Notifications

- `app/Notifications/OrganizationInvitationNotification.php` — the invite email.
- `app/Notifications/UserMagicLink.php` — the one-time sign-in link.

Both are mailed through the log mailer in a fresh clone, so a local invite lands
in `storage/logs` rather than needing SMTP credentials ([[operations/runtime]]).

Where the history is shown, and how long it is kept, is in
[[features/account-settings]].
