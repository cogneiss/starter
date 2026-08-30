---
title: Events, listeners and notifications
status: current
supersedes: []
code_refs:
    - app/Events/OrganizationCreated.php
    - app/Events/OrganizationNotified.php
    - app/Support/OrganizationDatabaseChannel.php
    - app/Listeners/RecordSuccessfulLogin.php
    - app/Listeners/RecordFailedLogin.php
    - app/Notifications/OrganizationInvitationNotification.php
    - app/Notifications/UserMagicLink.php
    - app/Actions/SeedOrganizationRoles.php
updated: 2026-08-31
---

# Events, listeners and notifications

Small surface on purpose. Two first-party events, two listeners, two
notifications, one notification channel.

## Events

`app/Events/OrganizationCreated.php` fires when an organization is created.
Cloning the role templates into the new organization is
`app/Actions/SeedOrganizationRoles.php` — an action, so it can also be called
directly from a seeder or a command without dispatching anything
([[domains/authorization]]). It syncs only the permissions the catalog still
has, so an organization is never half-created because a template named a
permission that has since been renamed.

`app/Events/OrganizationNotified.php` carries an organization id and nothing
else. It broadcasts on that organization's private channel to tell open tabs to
refetch, so the notification body is never on the wire and a stale subscriber
learns only that something happened ([[domains/ux-realtime-notifications]]).

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

The invite also goes to the in-app inbox: it offers `mail` and `database`, and
`User::channelsFor()` narrows that to what the person has kept turned on
([[domains/models]]). `UserMagicLink` offers no such choice — a sign-in link is
not something anybody opts out of.

## The database channel

`app/Support/OrganizationDatabaseChannel.php` replaces Laravel's `database`
driver, bound once in `AppServiceProvider` ([[domains/organization-resolvers]]).
It writes the row, then fires `OrganizationNotified` for the bound organization.
Outside a tenant — a console notification, say — there is no channel to announce
on, so the row is written and nothing is broadcast, which is why the id is
checked rather than assumed.

Where the history is shown, and how long it is kept, is in
[[features/account-settings]].
