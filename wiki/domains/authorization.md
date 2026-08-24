---
title: Roles, permissions and policies
status: current
supersedes: []
code_refs:
    - app/Support/PermissionCatalog.php
    - app/Support/PermissionDefinition.php
    - app/Policies/OrganizationPolicy.php
    - app/Policies/OrganizationMembershipPolicy.php
    - app/Policies/OrganizationInvitationPolicy.php
    - app/Policies/RolePolicy.php
    - app/Models/Role.php
    - app/Models/RoleTemplate.php
    - app/Actions/SyncPermissions.php
    - app/Console/Commands/SyncPermissionsCommand.php
    - config/permission.php
updated: 2026-08-24
---

# Roles, permissions and policies

RBAC on `spatie/laravel-permission` with organization-scoped teams, configured in
`config/permission.php`. The same user can be an owner in one organization and a
member in another.

## Policies

Four ship, one per authorizable resource:

- `OrganizationPolicy`
- `OrganizationMembershipPolicy`
- `OrganizationInvitationPolicy`
- `RolePolicy`

Every method checks two gates — relationship and named permission. The reasoning
and the test that enforces it are in [[architecture/two-gate-authorization]].

## The permission catalog

`app/Support/PermissionCatalog.php` is the single list of permissions, each one a
`app/Support/PermissionDefinition.php` named `<resource>.<verb>`. Nothing else
declares a permission. `app/Actions/SyncPermissions.php` writes the catalog to
the database, exposed as:

```bash
php artisan app:sync-permissions
```

The action is separate from the command because it is also useful from a seeder
or a deploy step, which is the general pattern here
([[architecture/actions-and-controllers]]).

## Roles and templates

`app/Models/RoleTemplate.php` holds the global blueprints — owner, admin, member
— seeded by `RoleTemplateSeeder`. Creating an organization clones them into it as
`app/Models/Role.php` rows, so an organization can edit its own roles without
affecting anyone else's.

That is also why `Role` has no factory and is exempted in
`config/conventions.php` with a reason: the seeder writes it, not a factory
([[architecture/convention-guards]]).

A self-serve role builder UI was left out; `PermissionCatalog` is the data such a
UI would render ([[decisions/not-included]]).
