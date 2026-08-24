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
updated: 2026-08-24
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

- Members: a list with role and status, plus removal
  (`OrganizationMemberController`).
- Invitations: invite by email, resend, revoke
  (`OrganizationInvitationController`); accepting joins the organization
  (`OrganizationInvitationAcceptanceController` +
  `app/Actions/AcceptOrganizationInvitation.php`).
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

## Impersonation

A super admin can sign in as a user to reproduce a support issue
(`UserImpersonationController`, `StartImpersonation`, `StopImpersonation`).

Three things bound it:

- It is behind the `impersonation-enabled` flag, off by default
  ([[domains/feature-flags]]).
- Every session is written to `ImpersonationLog`.
- Destructive routes are blocked while impersonating, by the
  `not-impersonating` middleware.

`app/Support/Impersonation.php` owns the two session keys involved —
`impersonator_user_id` and `impersonation_log_id`. Read them through it rather
than touching the session directly, so the audit row and the session cannot drift
apart.
