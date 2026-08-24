---
name: resource-spine
description: "Use when adding, renaming or removing an Eloquent model in this starter kit, or when building a CRUD surface over one. Covers the six-method ResourceContract, the auto-discovered ResourceRegistry, the `php artisan app:make-resource` generator and what it writes, and the convention guards (G1 factory, G4 #[TypeScript], G5 resource adapter) that fail CI when a model skips them. Do not use for authorization or organization scoping (see org-access) or for choosing which test command to run (see testing-gates)."
license: MIT
metadata:
    author: cogneiss
---

# Resource spine

Every user-facing model in this kit has one adapter class. If you are adding a
model, you are adding an adapter, or CI goes red.

## Adding a model: run the generator, do not hand-write the files

```bash
php artisan app:make-resource Project
```

`app/Console/Commands/MakeResourceCommand.php` writes, from `stubs/resource/*.stub`:

- `app/Models/Project.php`, a migration, `database/factories/ProjectFactory.php`
- `app/Data/ProjectData.php` (carries `#[TypeScript]`)
- `app/Policies/ProjectPolicy.php`, `app/Actions/CreateProject.php`
- `app/Http/Requests/StoreProjectRequest.php`, a controller with `create`/`store` only
- `app/Resources/Definitions/ProjectResource.php`
- the Inertia create page, the `routes/web.php` line, the `PermissionCatalog` entry
- four tests: model, action, controller, Data

Options: `--dry-run` (list, write nothing), `--force` (overwrite clashes),
`--no-migration`, `--base=` (generate elsewhere; used by the generator's own test).
The name must be alphabetic and singular studly — `Project`, `BlogPost`.

It stops at `create`/`store` on purpose. Everything it emits passes
`composer test` unedited, coverage gate included. A generated `update`/`destroy`
with no test behind it fails `--exactly=100.0` on the first run. Write the
methods you actually need, with their tests; do not delete generated code to get
green.

## The contract: exactly six methods

`app/Resources/ResourceContract.php`:

| Method                | Returns                                               |
| --------------------- | ----------------------------------------------------- |
| `key(): string`       | stable plural slug, e.g. `'organizations'`            |
| `label(): string`     | human name                                            |
| `model(): string`     | model class-string                                    |
| `dataClass(): string` | the `app/Data` class that carries it to the page      |
| `policy(): ?string`   | policy class-string, `null` only if nothing guards it |
| `url(Model $record)`  | link to one record                                    |

Copy the shape from `app/Resources/Definitions/OrganizationResource.php`.

Build `url()` from a Wayfinder helper or `route()`, never a string literal —
`.ai/rules/resources.md` requires it so a renamed route breaks the build instead
of shipping a dead link.

**Do not add a seventh method.** `searchQuery()`, `visibleTo()`, `actions()`, API
exposure — none of their consumers exist here. If you genuinely need one, name
the consumer in the PR description. `app/Resources/ResourceRegistry.php` is the
seam to hang it on.

## Discovery and caching

`ResourceRegistry` scans `app/Resources/Definitions`. No registration call, no
service provider edit — dropping the file in is the registration.

- Duplicate `key()` is a hard failure, not last-one-wins.
  `tests/Fixtures/Resources/Duplicate` proves it.
- Unknown key throws `App\Exceptions\UnknownResource`.
- Production: `php artisan resource:cache`, undone by `php artisan resource:clear`.
  If a new definition does not appear, you are on a stale cache — clear it.

## The three guards that will fail you

`tests/Unit/Conventions/ConventionTest.php`:

| Guard | Requires                                                                 | Fix                                                    |
| ----- | ------------------------------------------------------------------------ | ------------------------------------------------------ |
| G1    | every model in `app/Models` has a factory                                | `php artisan make:factory`                             |
| G4    | every `app/Data` class, and every `dataClass()`, carries `#[TypeScript]` | add the attribute, then `composer typescript:generate` |
| G5    | every model has a resource adapter                                       | `php artisan app:make-resource <Name>`                 |

Each failure message names the command that fixes it. Read the message before
reaching for `config/conventions.php`.

### If a model genuinely should not have an adapter

Add it to `config/conventions.php` under `non_resource_models`, keyed by class,
**with a reason string as the value**:

```php
'non_resource_models' => [
    LoginHistory::class => 'Audit rows a user never navigates to.',
],
```

The reason is the point — a bare list of exempt names tells a reviewer nothing
and never gets revisited. Never weaken the guard itself, and never widen it to a
namespace.

## After you touch a Data class

`composer typescript:generate` rewrites `resources/js/types/generated.d.ts`.
Commit it. `php artisan app:doctor` checks whether the committed file is stale,
and CI fails if it is.

## Checklist before you push

1. `php artisan test --compact --filter=Convention` — G1/G4/G5.
2. `composer typescript:generate` and commit the diff, if any Data class moved.
3. `composer test:fast` while iterating, `composer test` before pushing.
4. If you edited a file some wiki page cites, run `/document` in the same change
   — `wiki:lint` blocks on it.

## Why it is shaped this way

- `wiki/architecture/six-method-spine.md` — why six, and which method earns the pattern
- `wiki/domains/resources.md` — the registry, the generator, what ships
- `wiki/architecture/convention-guards.md` — G1/G4/G5 and the exception format
- `wiki/decisions/resource-spine.md` — what was deliberately left out
