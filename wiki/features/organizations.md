---
title: Organizations
status: current
supersedes: []
code_refs:
    - app/Http/Controllers/OrganizationController.php
    - app/Http/Controllers/OrganizationMemberController.php
    - app/Http/Controllers/OrganizationInvitationController.php
    - app/Http/Controllers/OrganizationInvitationAcceptanceController.php
    - app/Http/Controllers/OrganizationSwitchController.php
    - app/Http/Controllers/UserImpersonationController.php
    - app/Actions/CreateOrganization.php
    - app/Actions/AcceptOrganizationInvitation.php
    - app/Actions/SuspendOrganizationMembership.php
    - app/Actions/ReactivateOrganizationMembership.php
    - app/Actions/AssertNotLastActiveOwner.php
    - app/Actions/StartImpersonation.php
    - app/Actions/StopImpersonation.php
    - app/Support/Impersonation.php
    - app/Http/Middleware/ReadOnlyWhileImpersonating.php
    - app/Support/ResourceQuery.php
    - app/Http/Middleware/EnsureTwoFactorEnabled.php
    - app/Listeners/RecordModelActivity.php
    - app/Listeners/RecordSuccessfulLogin.php
    - app/Http/Middleware/EnsureUserIsActive.php
    - app/Console/Commands/EndStaleImpersonationsCommand.php
    - app/Actions/SummarizeAiUsage.php
    - app/Ai/Agents/InvitationDrafter.php
    - app/Actions/CreateOrganizationInvitation.php
    - app/Actions/ResendOrganizationInvitation.php
    - app/Actions/RevokeOrganizationInvitation.php
    - app/Actions/RemoveOrganizationMembership.php
    - app/Actions/UpdateOrganizationMembershipRole.php
    - app/Actions/SwitchOrganization.php
    - app/Actions/UpdateOrganization.php
    - app/Data/InviteMemberData.php
    - app/Enums/BulkMembershipAction.php
    - app/Exceptions/LastActiveOwnerException.php
    - app/Http/Controllers/OrganizationMemberBulkController.php
    - app/Http/Requests/BulkOrganizationMembershipRequest.php
    - app/Http/Requests/CreateOrganizationInvitationRequest.php
    - app/Http/Requests/CreateOrganizationRequest.php
    - app/Http/Requests/UpdateOrganizationMembershipRequest.php
    - app/Http/Requests/UpdateOrganizationRequest.php
    - app/Http/Requests/UpdateOrganizationSwitchRequest.php
updated: 2026-11-19
---

# Organizations

The user-facing half of [[domains/multi-tenancy]].

## Switcher

Users in more than one organization switch from the sidebar
(`OrganizationSwitchController`). The switcher is hidden for anyone in a single
organization, so a solo sign-up never sees it. Signing up alone still creates a
personal organization behind the scenes — the scoping machinery is always on, the
UI just does not mention it.

## Members and invitations

Both screens are the list kit pointed at a different resource, so search,
filters, sorting, column controls, saved views, bulk actions and CSV export come
from `ListsResources` rather than from either controller
([[domains/ux-list-kit]]). Invitations moved off the members screen and onto a
list of their own the moment there were more of them than fit underneath it.

Route model binding is gone from both. A member or invitation is looked up with
`findListed()`, inside the organization's own query, so an id from another
organization is a 404 rather than a policy decision about a record that exists.
The `?peek=` drawer uses the same lookup and has no other way to be handed a
record ([[domains/multi-tenancy]]).

An inline role edit that the server refuses flashes the validation message as a
toast before rethrowing, because the row snaps back to the last server value the
instant the patch fails and a silent revert tells the person nothing.

- Members: role, status, removal (`OrganizationMemberController`).
- Invitations: invite by email, resend, revoke
  (`OrganizationInvitationController`); accepting joins the organization
  (`OrganizationInvitationAcceptanceController` +
  `app/Actions/AcceptOrganizationInvitation.php`). The acceptance form is
  unauthenticated and public, so it carries the same honeypot and dwell-time
  friction as the other public forms ([[operations/ops-csp]]).
- `app/Actions/AssertNotLastActiveOwner.php` is called on the paths that could
  strand an organization — removing or demoting a member — so the last active
  owner cannot be removed or demoted. It is an action rather than a check inside
  each controller precisely because it has several callers.

## Membership and invitation actions

Each write is an action in `app/Actions`, called by the controller above it.

- `app/Actions/CreateOrganizationInvitation.php` refuses an email that is
  already a member (validation error on `email`). If that email already has an
  unaccepted invitation it hands the existing one to
  `app/Actions/ResendOrganizationInvitation.php` instead of creating a second.
  Otherwise it stores a sha256 hash of a random 40 character token, sets expiry
  seven days out and mails the plain token. Only the hash is kept.
- `ResendOrganizationInvitation` rotates the token, pushes expiry seven days
  out and mails again, so the old link stops working. It refuses an accepted
  invitation. In the code read for this page its only caller is
  `CreateOrganizationInvitation`; whether a resend route exists is not
  documented here.
- `app/Actions/RevokeOrganizationInvitation.php` deletes the invitation row.
- `app/Actions/UpdateOrganizationMembershipRole.php` looks the role up by name
  inside the membership's organization, and fails with a validation error on
  `role` if it is not there. It calls `AssertNotLastActiveOwner` unless the
  target role is protected, then replaces the user's roles under `runAs()`. It
  writes its own `Activity` row (`role_changed`) because role changes land in
  pivot tables the wildcard audit listener does not see.
- `app/Actions/RemoveOrganizationMembership.php` asserts the last-owner rule,
  then in one transaction clears the user's roles in that organization, nulls
  `current_organization_id` if it pointed there, deletes the user's `AiMemory`
  rows for the organization and deletes the membership.
- `app/Exceptions/LastActiveOwnerException.php` is a `ValidationException`
  subclass with no body. `AssertNotLastActiveOwner` throws it with a
  `membership` message, so the refusal reaches the form as a validation error.
- `app/Actions/SwitchOrganization.php` throws `AuthorizationException` unless
  `belongsToOrganization()` is true, then sets `current_organization_id` and
  regenerates the session.
- `app/Actions/UpdateOrganization.php` updates the bound organization with the
  validated attributes and nothing else.

## Bulk membership actions

`app/Http/Controllers/OrganizationMemberBulkController.php` applies one action to
a selection of members through `ApplyBulkAction`. `app/Enums/BulkMembershipAction.php`
has three cases. `Suspend` and `Reactivate` map to the `update` ability and
`Remove` maps to `delete`, so the bulk path asks the same policy the single
record routes do, per record. The controller's own gate is only
`members.view`. It refuses to remove the acting user's own row with a validation
error on `ids`, and reports row by row so the screen can name members that were
left alone. `app/Http/Requests/BulkOrganizationMembershipRequest.php` validates
`action` as that enum, `ids` as strings and `allMatching` as a boolean; absent
`allMatching` means the visible page, true means every record the filters match.

## Form requests

| Request | Rules it holds |
| --- | --- |
| `app/Http/Requests/CreateOrganizationRequest.php` | `name`, required, max 255 |
| `app/Http/Requests/UpdateOrganizationRequest.php` | `name`; `slug` lowercase, kebab-case regex, unique among organizations except the bound one; `require_two_factor` boolean |
| `app/Http/Requests/UpdateOrganizationSwitchRequest.php` | `organization` must be a uuid that exists and is not soft-deleted; `bail` and `uuid` come first because PostgreSQL errors on a non-uuid compared to a uuid column |
| `app/Http/Requests/CreateOrganizationInvitationRequest.php` | `email` lowercase, max 255, `ValidEmail`; `role` string, max 255 |
| `app/Http/Requests/UpdateOrganizationMembershipRequest.php` | `role` and `status` (`MembershipStatus`), each `sometimes` and required when present |

None of these checks that the role exists or that the user may do the thing.
The action checks the role and the policy checks the person.

## Invitation payload for the AI path

`app/Data/InviteMemberData.php` is the typed payload of an `invite-member`
proposal: `email` (required, valid email) and `role` (required, max 255). The
model fills it in and validation rejects invented fields or shapes before
`app/Ai/Actions/InviteMember.php` runs ([[domains/ai-confirm-tokens]]).

## Suspension, not deletion

`SuspendOrganizationMembership` and `ReactivateOrganizationMembership` move a
membership through `MembershipStatus`. A suspended member keeps their data and
loses their access, and reactivation restores nothing because nothing was
destroyed. Deleting the membership would take the audit trail with it.

## Organization settings

Name, and a switch requiring two-factor from every member — enforced by the
`two-factor` middleware rather than by this screen
([[features/authentication]]).

## AI usage

`settings/organization/ai-usage` shows what this organization has spent on AI in
the last thirty days — runs, tokens and cost, split by agent and by tier. It is
`OrganizationController::aiUsage()` rendering whatever
`app/Actions/SummarizeAiUsage.php` returns, which is the same action
`php artisan ai:usage` prints, so the page and the console cannot disagree.

Viewing it needs the `view` policy on the organization, and the figures come from
the bound organization rather than an id in the URL
([[domains/ai-metering-and-quotas]]).

## Drafted invitations

`app/Ai/Agents/InvitationDrafter.php` writes the message body for an invitation.
It cannot send one: the agent returns a proposal, a confirm token is minted, and
`app/Ai/Actions/InviteMember.php` runs only after a member with the permission
confirms it ([[domains/ai-confirm-tokens]]). An agent that could invite people
into an organization on its own is an agent that can add a member nobody chose.

## Impersonation

A platform super admin can sign in as a user to reproduce a support issue
(`UserImpersonationController`, `StartImpersonation`, `StopImpersonation`). The
session is read-only and every step is audited.

- It is behind the `impersonation-enabled` flag, off by default
  ([[domains/feature-flags]]).
- Starting is `user-impersonation.store`, inside the `EnsurePlatformAdmin`
  group in `routes/web.php`, so anyone who is not platform staff gets a 404.
  Stopping, `user-impersonation.destroy`, stays outside that group because the
  signed-in user during impersonation is the target, who is not staff.
- `app/Http/Middleware/ReadOnlyWhileImpersonating.php` runs for every web request
  after `EnsureUserIsActive`. It refuses (403) any non-GET request except
  stopping, logging out, the `broadcasting/auth` handshake and a precognitive
  request on a route that carries `HandlePrecognitiveRequests`. It also refuses
  the reads that expose the target's credentials, second factors or exports:
  `user/two-factor-*`, `user/passkeys*`, `user/confirm-password*`, the
  `two-factor.*` and `passkey.*` routes, `browser-session.show`,
  `import.download` and `gdpr-export.download`, plus any request that accepts
  `text/csv` (`ResourceQuery::wantsCsv()` in `app/Support/ResourceQuery.php`),
  so a list export is refused too. The nine `not-impersonating`
  route guards remain and also guard GET pages.
- Both ends clear `auth.password_confirmed_at`, so neither identity inherits the
  other's password confirmation. If the impersonator cannot be loaded when
  stopping, the session is signed out and redirected to login. The log is closed
  either way.
- Every session is written to `ImpersonationLog`. `RecordModelActivity` bridges
  its creation and the change of `ended_at` into the activity log, and every
  activity row written during impersonation carries `properties.impersonator_id`
  (the causer stays the target).
- Starting and stopping write no `LoginHistory` row: `RecordSuccessfulLogin`
  skips while `Impersonation::active()`, and the keys are remembered before and
  forgotten after the sign-in.
- `EnsureTwoFactorEnabled` does not hold an impersonated member at the setup
  screen, so a forced-2FA organization cannot trap the admin.
- The banner strings live in `ui.impersonation.*` in `lang/en/ui.php` and
  `lang/nl/ui.php`.

`app/Support/Impersonation.php` owns the two session keys involved,
`impersonator_user_id` and `impersonation_log_id`. Read them through it rather
than touching the session directly, so the audit row and the session cannot drift
apart.

The log always ends. A target deactivated mid-session is caught by
`EnsureUserIsActive` (`app/Http/Middleware/EnsureUserIsActive.php`), which ends
the impersonation through `StopImpersonation` and hands the session back to the
admin instead of signing everybody out. A session that simply expires sends no
request, so every impersonated request stamps the log's `updated_at`
(`Impersonation::heartbeat()`), and the hourly `impersonation:end-stale`
command (`app/Console/Commands/EndStaleImpersonationsCommand.php`) closes a log
idle for a whole `session.lifetime`, with `ended_at` set to its last use.

`StartImpersonation` refuses a deactivated target. The activity row for an
impersonation is filed under the log's own `organization_id`, never the bound
organization, which at start is the admin's. A target with no organization gets
a platform-level row with a null organization (`app/Listeners/RecordModelActivity.php`).
