---
title: Decision log — organizations and access
status: current
supersedes: []
code_refs:
    - todo/org-access.status.json
updated: 2026-11-17
---

# Decision log — organizations and access

Thirteen phases: organizations and fail-closed scoping, roles and the permission
catalog, the two-gate authorization guard, feature flags, the membership
lifecycle, audited impersonation, the auth driver seam, social login,
per-organization forced 2FA, browser sessions and login history, do-not-track and
active-user enforcement, documentation, and a full-gate phase.

`todo/org-access.status.json` is the record. Phase 13, "full gate green", is
`failing` there — the plan's own binary rule is that unproven means failing, and
that phase was never proved on a clean run.

## The decisions that outlived the plan

- **Scoping fails closed.** No organization bound and strict mode on means an
  exception, not an unscoped query ([[architecture/fail-closed-scoping]]).
- **Two gates, not one.** A policy answers "may this actor touch this record"; a
  named permission answers "is this action allowed for this role". Both, always
  ([[architecture/two-gate-authorization]]).
- **The organization is resolved before routing.** Middleware order is the
  contract ([[architecture/resolve-before-routing]]).
- **Auth is one flow, not a driver zoo.** A driver seam existed and was removed
  once only the password driver was ever registered; the SSO warnings now live on
  `SocialAuthController` ([[features/authentication]], [[domains/auth-drivers]]
  superseded).

## What was considered and left out

Host classifiers, custom domains with on-demand TLS, a self-serve role builder UI,
plan catalogs and seat quotas, SAML/OIDC drivers, cross-organization access
requests, and device fingerprinting — with the reason for each in
[[decisions/not-included]].
