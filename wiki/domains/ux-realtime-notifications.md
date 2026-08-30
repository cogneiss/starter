---
title: In-app notifications and realtime
status: current
supersedes: []
code_refs:
    - app/Events/OrganizationNotified.php
    - app/Support/OrganizationDatabaseChannel.php
    - routes/channels.php
    - app/Http/Controllers/NotificationController.php
    - app/Http/Controllers/NotificationBulkController.php
    - app/Http/Controllers/UserNotificationPreferenceController.php
    - resources/js/hooks/use-organization-channel.ts
    - resources/js/components/notification-bell.tsx
    - resources/js/components/notification-panel.tsx
    - tests/Feature/NotificationScopeTest.php
    - tests/Feature/InAppNotificationsTest.php
    - tests/Feature/ChannelAuthorizationTest.php
    - tests/Feature/RealtimeNoKeyTest.php
    - tests/Mutations/phase10-channel.patch
    - tests/Mutations/phase10-unread-scope.patch
updated: 2026-08-31
---

# In-app notifications and realtime

A bell with an unread count, a panel that lists what happened, and a websocket
that keeps both current. Realtime is an optimisation here, not a dependency: the
application is fully usable with no broadcaster configured at all.

## Notifications belong to a tenant

`App\Support\OrganizationDatabaseChannel` extends Laravel's database channel and
stamps the organization onto the row as it is written. Reading is then a where
clause rather than a guess, which matters for the common case of one person in
two organizations: without the stamp they would carry the first tenant's unread
count into the second.

Every read — the bell count shared by `HandleInertiaRequests`, the panel, the
mark-as-read routes — starts from `(user, organization)`. Someone else's
notification id is not found rather than refused, so it answers 404.
`tests/Mutations/phase10-unread-scope.patch` removes the organization from the
unread query and `tests/Feature/NotificationScopeTest.php` is what notices.

## The broadcast carries nothing

`App\Events\OrganizationNotified` has an empty payload on purpose. It broadcasts
as `notification.created` on `PrivateChannel('organization.{id}')` and says only
_something happened here_; every listening tab then re-reads its own scoped
count. A payload on a shared channel would have to be correct for every listener
on it, and one member may not be allowed to see what another was told.

Outside a tenant — a console notification, say — the row is written and nothing
is announced.

## Who may listen

`routes/channels.php` runs the same two gates the policies do: an active
membership row for this user and this organization, then the ability. A
websocket is a read, and a person can hold a membership without being allowed
to read the organization.

```bash
bin/prove-control.sh phase10-channel ChannelAuthorization
bin/prove-control.sh phase10-unread-scope NotificationScope
```

## No key is a supported state

`use-organization-channel.ts` returns `null` when `VITE_REVERB_APP_KEY` is
absent. No Echo instance is constructed, no connection is attempted, no error is
logged — the bell simply updates on the next page visit.
`tests/Feature/RealtimeNoKeyTest.php` and `tests/Feature/ZeroKeyBootTest.php`
keep a fresh clone with an empty `.env` booting and passing.

## Preferences

`UserNotificationPreferenceController` stores which notifications a person wants
by mail. These are per-user and durable, so they live in the database rather
than in the URL or in `localStorage` — see [SETUP.md](../../SETUP.md) for where
each kind of preference in this application is kept and why.

## Related

- [[domains/events-and-notifications]] — the notification classes themselves
- [[domains/multi-tenancy]] — the organization the stamp comes from
