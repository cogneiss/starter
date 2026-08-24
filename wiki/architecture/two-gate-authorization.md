---
title: Why authorization needs two gates
status: current
supersedes: []
code_refs:
    - app/Policies/OrganizationPolicy.php
    - app/Support/PermissionCatalog.php
    - tests/Unit/AuthorizationConventionTest.php
    - .ai/rules/policies.md
updated: 2026-08-24
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

## Permission names are data, not strings

`app/Support/PermissionCatalog.php` is the single list, each entry a
`PermissionDefinition` named `<resource>.<verb>`, lowercase, dot-separated. A
permission that is not in the catalog fails the convention test. That matters
because the failure mode of a typo'd permission string is a silent deny: the user
sees a 403, the log shows nothing wrong, and the string looks right in review.

`php artisan app:sync-permissions` writes the catalog to the database. The
catalog is the source; the table is a projection of it.

Details of the roles, templates and teams setup are in [[domains/authorization]].
