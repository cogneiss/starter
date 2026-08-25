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
    - app/Actions/SummarizeAiUsage.php
    - app/Ai/Agents/InvitationDrafter.php
updated: 2026-08-25
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
