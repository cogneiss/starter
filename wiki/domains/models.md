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
updated: 2026-08-31
---

# Models

Twenty models ship in `app/Models`. Seven carry the core domain; three
(`FeatureOverride`, `Role`, `RoleTemplate`) belong to [[domains/feature-flags]]
and [[domains/authorization]]; five belong to the AI layer
([[domains/ai-blocks]], [[domains/ai-memory]]); and five arrived with the UX
layer — `SavedSearch` ([[domains/ux-filters-and-saved-searches]]),
`OnboardingProgress` ([[domains/ux-onboarding]]), and `ImportBatch`,
`ImportRow` and `TempUpload` ([[domains/ux-import-and-uploads]]). None of the
last five is a record a person navigates to, which is the reason each carries a
line in `config/conventions.php` rather than a resource adapter.

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

## Two models grew columns for the UX layer

`Organization` carries `brand_primary_color` and `brand_accent_color`, both
nullable — an organization that has chosen nothing gets the kit's palette rather
than a stored copy of it ([[domains/ux-branding]]).

`User` carries `locale` ([[domains/ux-i18n]]) and `notification_preferences`, and
the second one is worth reading in the source. `User::NOTIFICATION_CHANNELS` is
the list of notifications a person may turn off, and it names one:
`organization_invitation_notification` on `mail` and `database`. A notification
that carries a security decision is absent on purpose, because a magic link is
not something anybody opts out of.

`channelsFor()` filters what a notification already offers and can never add a
channel it does not support. Nothing recorded means everything is wanted, so a
newly added channel reaches people without a backfill and a person who has never
opened the settings screen is not silently opted out.
