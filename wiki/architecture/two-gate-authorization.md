---
title: Why authorization needs two gates
status: current
supersedes: []
code_refs:
    - app/Policies/OrganizationPolicy.php
    - app/Support/PermissionCatalog.php
    - tests/Unit/AuthorizationConventionTest.php
    - .ai/rules/policies.md
updated: 2026-09-01
---

# Why authorization needs two gates

Every policy method checks two things: a relationship to the record, and a named
permission.

```php
return $this->context->id() === $organization->id && $user->can('organization.update');
```

`app/Policies/OrganizationPolicy.php` injects `OrganizationContext` to do it. The
test reads the method body, so the organization check has to be visible there as
`$this->context->id()` or `organization_id` — a helper that hides it fails the
convention even when the logic is right.

`tests/Unit/AuthorizationConventionTest.php` enforces it, so a policy method that
checks one gate fails the suite rather than shipping.

## What each gate is for

The relationship check answers "is this user anywhere near this record". The
permission answers "does their role include this verb". They fail differently:

- Relationship only — every member of an organization can do everything to it.
  Roles become decoration.
- Permission only — a user with `organization.update` can update _any_
  organization, because a permission is a role-level fact and knows nothing about
  which record is in front of it. Under `spatie/laravel-permission` with
  organization-scoped teams the blast radius is smaller, but "the right verb in
  the wrong organization" is exactly the bug tenancy work exists to prevent.

Neither is a subset of the other, so the test asks for both rather than accepting
whichever the author remembered.

## The two exceptions, and what they cost to add

`tests/Unit/AuthorizationConventionTest.php` keeps an `$exceptions` list of
`Class@method` entries, each carrying a comment. It holds exactly two:
`SavedSearchPolicy@manage` and `OnboardingProgressPolicy@manage`. Both are one
person's own record — a kept view of a list, a decision to skip the checklist —
so there is no role-level verb to name and no permission to hold. **Both still
check the organization.** The exemption is from the permission gate only; the
relationship gate has no exemptions at all.

Adding an entry means writing the sentence that says why, in the file, in the
diff. That is the entire enforcement mechanism, and it is enough because the
sentence is what a reviewer argues with.

## A permission can depend on the record

`RolePolicy::grant()` is the one place the two gates are not independent: it
asks for `organization.update` when the role is protected and `members.invite`
when it is not. Handing out a protected role is handing out the organization,
so it takes the organization permission. The bulk importer asks this per row,
which is why it exists — one file can name two roles and be answered
differently about each ([[domains/ux-import-and-uploads]]).

## Permission names are data, not strings

`app/Support/PermissionCatalog.php` is the single list, each entry a
`PermissionDefinition` named `<resource>.<verb>`, lowercase, dot-separated. A
permission that is not in the catalog fails the convention test. That matters
because the failure mode of a typo'd permission string is a silent deny: the user
sees a 403, the log shows nothing wrong, and the string looks right in review.

`php artisan app:sync-permissions` writes the catalog to the database. The
catalog is the source; the table is a projection of it. The UX layer added two
entries under an `Imports` group — `imports.view` and `imports.run` — split
because seeing which lines of a file failed and creating records from a file in
bulk are not the same trust. The platform ops layer added six more, split the
same way: `api.tokens.view` and `api.tokens.manage`, `api.usage.view`,
`audit.view`, and `webhooks.view` and `webhooks.manage` — seeing a credential,
a usage number, an audit trail or a delivery log is never the same trust as
creating or revoking one.

`OrganizationPolicy::viewAny()` is worth one line of its own: with no record to
relate to, the relationship gate becomes "an organization is bound at all"
(`$this->context->id() !== null`). That is the honest form of the same check,
and it is what the list surfaces call before they build a query.

Details of the roles, templates and teams setup are in [[domains/authorization]].
