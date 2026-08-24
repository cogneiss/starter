---
name: org-access
description: "Use when touching anything tenant-scoped or authorization-related in this starter kit: models using BelongsToOrganization, policies, permissions, roles and role templates, membership and invitations, queued jobs that read scoped data, or tests that query scoped models. Covers OrganizationContext and runAs(), the fail-closed global scope, the two-gate policy rule the convention test enforces, and PermissionCatalog. Getting this wrong writes a cross-organization data leak, so read it before editing, not after a test fails."
license: MIT
metadata:
    author: cogneiss
---

# Organizations and access

Getting this wrong does not throw. It returns another organization's rows and
renders them correctly. Read this before you write the query.

## Rule 1 — scoped models need an organization bound

A model using `App\Concerns\BelongsToOrganization` gets a global scope filtering
on `organization_id`, and has `organization_id` filled on create. Both read
`App\Support\OrganizationContext`, a singleton.

With nothing bound, a query throws `App\Exceptions\OrganizationContextMissing`.
That is deliberate: returning everything is a breach, returning nothing is a
ticket nobody files. See `config/organizations.php`.

In a test, a job, a command or a seeder, bind it explicitly:

```php
resolve(OrganizationContext::class)->runAs($organization, function (): void {
    // scoped queries in here see $organization
});
```

`runAs()` restores the previous context afterwards, so nesting is safe. It also
syncs the `spatie/laravel-permission` team id, which is why `$user->can()` inside
it resolves against the right organization's roles.

**Never** "fix" a missing-context failure by setting `ORGANIZATIONS_STRICT=false`.
That switch exists for migrating a legacy database with rows predating the
column, nothing else. A failing test needs `runAs()`.

## Rule 2 — one escape hatch, and it is named

```php
// Admin report: counts across every organization, by design.
Project::withoutOrganizationScope()->count();
```

`withoutOrganizationScope()` is greppable and carries a comment saying why
(`.ai/rules/app.md`). A hand-written `where('organization_id', $id)` is not an
escape hatch — it is a second implementation of the scope that will disagree with
the first one the day the scope changes. Do not write one.

## Rule 3 — queued jobs must rebind

A job touching scoped models implements `App\Contracts\OrganizationAware`
(`organizationId(): ?string`) and returns
`App\Queue\Middleware\WithOrganizationContext` from `middleware()`. Skip it and
the job runs under whatever organization the previous job on that worker left
bound. That is not an error, it is the wrong tenant's data, and it looks like it
worked.

## Rule 4 — every policy method checks two gates

`tests/Unit/AuthorizationConventionTest.php` enforces both, per public method:

```php
public function update(User $user, Project $project): bool
{
    return $this->context->id() === $project->organization_id
        && $user->can('projects.update');
}
```

- The organization check must be visible in the method body as
  `$this->context->id()` or `organization_id`. Inject `OrganizationContext` in the
  constructor, as `app/Policies/OrganizationPolicy.php` does.
- The permission check must be `->can(` or `->hasPermissionTo(`.

Relationship alone means every member can do everything and roles are decoration.
Permission alone means the right verb in the wrong organization. Neither is a
subset of the other, so both are required.

The same test asserts that every model using `BelongsToOrganization` has a
policy at all.

If a method genuinely needs one gate, add a `Policy@method` entry to the
`$exceptions` array at the top of that test file with a comment saying why. Never
loosen the regex, never blanket-skip a class.

## Rule 5 — permissions come from the catalog

`app/Support/PermissionCatalog::all()` is the only place a permission is
declared. Names are `<resource>.<verb>`, lowercase, dot-separated — existing ones
are `organization.view/update/delete`, `members.view/invite/update/remove`,
`roles.view/manage`. Add yours there, as a `PermissionDefinition` with its group,
label and description, then:

```bash
php artisan app:sync-permissions
```

which runs `app/Actions/SyncPermissions.php`. The catalog is the source; the
database table is a projection. A permission string not in the catalog fails the
convention test — which is the point, because the runtime failure mode of a typo
is a silent 403 with nothing in the log.

## Roles and templates

`app/Models/RoleTemplate.php` holds the global blueprints (owner, admin, member),
seeded by `RoleTemplateSeeder`. Creating an organization clones them into
`app/Models/Role.php` rows owned by that organization, so one organization
editing its roles cannot touch another's. The same user can be an owner in one
organization and a member in another — teams are on, see `config/permission.php`.

`Role` has no factory on purpose and is exempted in `config/conventions.php` with
that reason.

## Vocabulary

User-facing text says **organization**, never "tenant". Code and comments too.

## In tests

- Bind with `runAs()`, or use a factory that creates the membership:
  `User::factory()->forOrganization($organization)->create()`.
- Assert the negative case. A tenancy test that only proves the owner can see
  their own row proves nothing; add the other organization's user and assert 403
  or an empty result.

## Why it is shaped this way

- `wiki/architecture/fail-closed-scoping.md` — why unbound throws
- `wiki/architecture/two-gate-authorization.md` — why both gates
- `wiki/domains/multi-tenancy.md` — every moving part and where it lives
- `wiki/domains/authorization.md` — policies, catalog, roles, templates
- `wiki/domains/organization-resolvers.md` — session, subdomain, single
- `wiki/decisions/org-access.md` — what was left out
