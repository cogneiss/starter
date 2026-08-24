---
title: Models
status: current
supersedes: []
code_refs:
    - app/Models/User.php
    - app/Models/Organization.php
    - app/Models/OrganizationMembership.php
    - app/Models/OrganizationInvitation.php
    - app/Models/LoginHistory.php
    - app/Models/ImpersonationLog.php
    - app/Models/SocialAccount.php
    - app/Enums/MembershipStatus.php
updated: 2026-08-24
---

# Models

Ten models ship in `app/Models`. Seven carry the domain; three
(`FeatureOverride`, `Role`, `RoleTemplate`) belong to
[[domains/feature-flags]] and [[domains/authorization]].

| Model                    | Holds                                                      |
| ------------------------ | ---------------------------------------------------------- |
| `User`                   | the account; UUID primary key, sensitive attributes hidden |
| `Organization`           | the tenant, with a `slug` the subdomain resolver can match |
| `OrganizationMembership` | the join, carrying role and `MembershipStatus`             |
| `OrganizationInvitation` | a pending invite by email                                  |
| `LoginHistory`           | sign-in attempts, successful and failed                    |
| `ImpersonationLog`       | one row per impersonation session                          |
| `SocialAccount`          | an OAuth identity linked to a user                         |

`app/Enums/MembershipStatus.php` is what makes suspension a state rather than a
deletion: a suspended member keeps their rows and loses their access, and
reactivating them is a status change with nothing to restore.

## Conventions on a model here

- UUID primary keys on users, hidden sensitive attributes, and typed
  `@property-read` docblocks so PHPStan at level max can see the schema
  ([[architecture/type-safety]]).
- `final` and `declare(strict_types=1)`, enforced by the architecture preset.
- Organization-scoped models use `BelongsToOrganization` — never a hand-written
  `where('organization_id', ...)` ([[architecture/fail-closed-scoping]]).
- Every model has a factory (G1) and a resource adapter (G5), or a reason string
  in `config/conventions.php` ([[architecture/convention-guards]]).

`UserFactory` ships `unverified()` and `withoutTwoFactor()` states; check for a
state before hand-building a model in a test ([[operations/testing]]).
