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
updated: 2026-09-01
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

Two shapes are worth knowing before writing a fifth policy. A `viewAny()` has no
record to relate to, so the relationship gate becomes "an organization is bound
at all": `OrganizationPolicy::viewAny()`, `OrganizationMembershipPolicy::viewAny()`
and `OrganizationInvitationPolicy::viewAny()` all read
`$this->context->id() !== null && $user->can(...)`. And a permission may depend on
the record: `RolePolicy::grant()` requires `organization.update` for a protected
role and only `members.invite` for an ordinary one, because handing someone the
owner role is a different act from handing them a role the organization invented.

Neither shape is an exemption. A list that passes `viewAny()` still returns rows
from a query narrowed to the bound organization, so a foreign id is absent rather
than forbidden ([[domains/multi-tenancy]]).

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

The UX layer added one group to the catalog — `Imports`, holding `imports.view`
and `imports.run`. Reading a batch and starting one are separate permissions
because an import writes rows in bulk, and the person who wants to see what a
teammate uploaded is not necessarily the person allowed to run it
([[domains/ux-import-and-uploads]]).

The platform ops layer added three more groups the same way: `API`
(`api.tokens.view`, `api.tokens.manage`, `api.usage.view`), `Audit`
(`audit.view`), and `Webhooks` (`webhooks.view`, `webhooks.manage`). Viewing and
managing stay separate permissions in each case, for the same reason reading and
running an import do ([[operations/ops-api-tokens]], [[operations/ops-audit-log]],
[[operations/ops-webhooks]]).

## Roles and templates

`app/Models/RoleTemplate.php` holds the global blueprints — owner, admin, member
— seeded by `RoleTemplateSeeder`. Creating an organization clones them into it as
`app/Models/Role.php` rows, so an organization can edit its own roles without
affecting anyone else's.

Cloning drops permission names the catalog no longer has:
`SeedOrganizationRoles` syncs only the permissions that exist for the guard, so a
template written against an older catalog still creates its organization instead
of failing halfway through.

That is also why `Role` has no factory and is exempted in
`config/conventions.php` with a reason: the seeder writes it, not a factory
([[architecture/convention-guards]]).

A self-serve role builder UI was left out; `PermissionCatalog` is the data such a
UI would render ([[decisions/not-included]]).
