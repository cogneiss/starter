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
    - app/Policies/ActivityPolicy.php
    - app/Policies/AiAuditLogPolicy.php
    - app/Policies/AiConfirmTokenPolicy.php
    - app/Policies/AiCreditLedgerEntryPolicy.php
    - app/Policies/AiDocumentPolicy.php
    - app/Policies/ApiRequestLogPolicy.php
    - app/Policies/OnboardingProgressPolicy.php
    - app/Policies/WebhookDeliveryPolicy.php
    - app/Policies/WebhookEndpointPolicy.php
updated: 2026-11-18
---

# Roles, permissions and policies

RBAC on `spatie/laravel-permission` with organization-scoped teams, configured in
`config/permission.php`, which turns teams on (`'teams' => true`) with
`organization_id` as the team foreign key and uses `App\Models\Role` as the role
model. The file holds only those overrides. Everything else, including the
24 hour permission cache (`cache.expiration_time`), comes from the package's own
config in `vendor/spatie/laravel-permission/config/permission.php`. The same user can be an owner in one organization and a
member in another.

## Policies

Four ship for the organization itself, one per authorizable resource, and nine more guard the AI, audit, API, webhook and onboarding records (next section):

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
## Policies for the platform and AI layers

Nine more policies follow the same shape. Each takes `OrganizationContext`, and
each method checks the bound organization and a named permission from
`app/Support/PermissionCatalog.php`. `viewAny()` and `create()` use
`$this->context->id() !== null`; the record methods use
`$this->context->id() === $record->organization_id`.

| Policy | Model | Methods and permission |
| --- | --- | --- |
| `app/Policies/ActivityPolicy.php` | `Activity` | `viewAny`, `view`: `audit.view` |
| `app/Policies/AiAuditLogPolicy.php` | `AiAuditLog` | `viewAny`, `view`: `ai.view` |
| `app/Policies/AiConfirmTokenPolicy.php` | `AiConfirmToken` | `viewAny`: `ai.view`; `view`: `ai.view` and the token's `user_id` must be the viewer |
| `app/Policies/AiCreditLedgerEntryPolicy.php` | `AiCreditLedgerEntry` | `viewAny`, `view`: `ai.view`; `create`: `ai.grant` |
| `app/Policies/AiDocumentPolicy.php` | `AiDocument` | `viewAny`, `view`: `ai.view` |
| `app/Policies/ApiRequestLogPolicy.php` | `ApiRequestLog` | `viewAny`, `view`: `api.usage.view` |
| `app/Policies/WebhookEndpointPolicy.php` | `WebhookEndpoint` | `viewAny`: `webhooks.view`; `create`, `update`, `delete`: `webhooks.manage` |
| `app/Policies/WebhookDeliveryPolicy.php` | `WebhookDelivery` | `viewAny`: `webhooks.view`; `replay`: `webhooks.manage` |
| `app/Policies/OnboardingProgressPolicy.php` | `OnboardingProgress` | `manage`: no permission, the row's `user_id` must be the user |

Notes from the files themselves:

- The activity, AI audit and API request logs are append-only, so the policies
  only cover reading. `ApiRequestLogPolicy` gates on `api.usage.view`, not
  `api.tokens.view`: the log is usage, and token visibility is a separate grant.
- `AiCreditLedgerEntryPolicy` splits reading usage (`ai.view`) from adding
  credit (`ai.grant`) on purpose: one is a usage question, the other money.
- `AiConfirmTokenPolicy` does not gate running a confirmation. The doc comment
  says `App\Actions\ConsumeConfirmToken` re-checks the proposed action's own
  ability, which is stronger than a verb on the token row. That action was not
  read for this page.
- `AiDocumentPolicy` uses `ai.view` because an indexed document is organization
  content a member already has, reached through an agent.
- `WebhookEndpointPolicy` treats endpoints as the organization's, not the
  creator's, so anyone with `webhooks.manage` may edit or delete a colleague's.
  Replaying a delivery re-sends data to an external receiver, hence `manage`.
- `OnboardingProgressPolicy` is one of the two exceptions in
  `tests/Unit/AuthorizationConventionTest.php` and has no permission gate
  ([[architecture/two-gate-authorization]]). It still compares the organization.
  Its doc comment says the real gate is the where clauses in
  `OnboardingProgress::ownedBy()`, so a foreign row is missing rather than
  refused, and the policy repeats the pair as a second lock.

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
