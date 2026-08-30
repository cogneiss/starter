---
title: The resource spine
status: current
supersedes: []
code_refs:
    - app/Resources/ResourceContract.php
    - app/Resources/ResourceRegistry.php
    - app/Resources/Definitions/UserResource.php
    - app/Resources/Definitions/OrganizationResource.php
    - app/Resources/Definitions/OrganizationMemberResource.php
    - app/Resources/Definitions/OrganizationInvitationResource.php
    - app/Exceptions/UnknownResource.php
    - app/Console/Commands/MakeResourceCommand.php
    - app/Console/Commands/ResourceCacheCommand.php
    - app/Console/Commands/ResourceClearCommand.php
updated: 2026-08-31
---

# The resource spine

One adapter per user-facing model in `app/Resources/Definitions`, auto-discovered
by `app/Resources/ResourceRegistry.php`. Five ship: user, organization,
organization member, organization invitation and AI confirm token. An unknown key
throws `app/Exceptions/UnknownResource.php`.

The thirteen methods, and why the contract grew from six, are in
[[architecture/six-method-spine]]. In short: search, the list kit and CSV export
all needed the same facts about a resource, so `searchable()`, `sortable()`,
`filters()`, `columns()`, `recordLabel()`, `recordDescription()` and
`scopedQuery()` were added together with the consumers that use them
([[domains/ux-search-and-palette]], [[domains/ux-list-kit]]).

`app/Resources/ScopedToOrganization.php` is the default implementation of the
last one, so an adapter over an ordinary scoped model writes no query at all. An
adapter that needs more than the model's own scope — `AiConfirmTokenResource`
narrows to the acting user as well — writes `scopedQuery()` by hand and says why
in a docblock.

## Discovery and caching

The registry scans the definitions directory. In production, cache it:

```bash
php artisan resource:cache    # writes the cached registry
php artisan resource:clear    # undoes it
```

Duplicate keys are a failure, not a last-one-wins — the fixtures under
`tests/Fixtures/Resources/Duplicate` exist to prove that.

## The generator

```bash
php artisan app:make-resource <Name>
```

`app/Console/Commands/MakeResourceCommand.php` scaffolds from
`stubs/resource/*.stub`: model, migration, factory, Data class, policy, action,
form request, controller (`create`/`store` only), resource adapter, the Inertia
create page, the route line, the permission catalog entry, and four tests — model,
action, controller and Data.

It generates less than a full CRUD set on purpose: everything it emits passes
`composer test` unedited, coverage gate included. A generated `update`/`destroy`
pair with no tests behind it would break the `--exactly=100.0` gate on the first
run, and the fix for that is to write the code you actually need rather than to
delete generated code.

`--dry-run`, `--force` and `--no-migration` are available.

Guard G5 fails CI for a model with no adapter, which is what makes the generator
the default path rather than a convenience
([[architecture/convention-guards]]). What the spine deliberately does not do is
listed in [[decisions/resource-spine]].
