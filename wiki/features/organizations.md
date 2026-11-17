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
    - app/Actions/SummarizeAiUsage.php
    - app/Ai/Agents/InvitationDrafter.php
updated: 2026-11-18
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

Known gaps, tracked in `todo/org-access.status.json`: a session that expires or
is torn down by `EnsureUserIsActive` leaves `ended_at` empty, the target is not
checked for being active, and a target with no organization gets no activity
row.
