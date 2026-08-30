---
title: Actions and cruddy controllers
status: current
supersedes: []
code_refs:
    - app/Actions/CreateOrganization.php
    - app/Actions/SyncPermissions.php
    - app/Http/Controllers/OrganizationController.php
    - app/Http/Controllers/OrganizationSwitchController.php
    - app/Services/.gitkeep
    - app/Actions/SummarizeAiUsage.php
    - tests/Unit/ArchTest.php
updated: 2026-08-31
---

# Actions and cruddy controllers

Business logic lives in `app/Actions`, one class with one `handle()` method.
Controllers translate HTTP into a call on an action and a response; they hold no
rules of their own.

## Why actions rather than services

An action has exactly one entry point, so its callers are interchangeable: an
HTTP controller, a queued job, an Artisan command, a test. `app/Actions/CreateOrganization.php`
is called from `app/Http/Controllers/OrganizationController.php` and from the
seeding path; neither knows about the other. A service class with eight public
methods cannot make that promise — every caller depends on the whole surface.

`app/Services/` exists as a directory and holds nothing but `.gitkeep`. That is
deliberate: the shape of the kit puts logic in actions, and the empty directory
is where a genuine long-lived collaborator (an API client, a wrapper around a
third-party SDK) would go. Nothing in the kit needed one yet.

## Cruddy by design

Controllers stay on the resource verbs — `create`, `store`, `edit`, `update`,
`show`, `destroy` — one resource each. When a verb does not fit, the answer is a
new controller for a new resource, not a seventh method:
`app/Http/Controllers/OrganizationSwitchController.php` exists because "switch
the current organization" is its own resource, an update on the switch, not a
method on the organization controller.

`tests/Unit/ArchTest.php` carries the architecture presets that keep this honest,
including the rule that controllers are used from routes and nowhere else. A
controller called from another controller is the first sign that the logic
belonged in an action.

`App\Http\Controllers\Concerns` is ignored by that rule, and it is the only
thing ignored. A trait exists to be used, and the traits there —
`ListsResources` among them ([[domains/ux-list-kit]]) — are used by controllers
only, so the rule would otherwise fail on the shared half of a controller rather
than on a controller calling another controller.

## Agents are callers, not a second home for logic

An AI agent is one more caller of an action, and the same rules bind it. The
`aiUsage()` method on `app/Http/Controllers/OrganizationController.php` renders
whatever `app/Actions/SummarizeAiUsage.php` returns; the aggregation, the pricing
and the thirty-day window live in the action, so `php artisan ai:usage` and the
page cannot disagree about the bill.

The direction only runs one way. `tests/Unit/ArchTest.php` asserts that nothing
in `App\Ai\Agents` touches `DB`, `ConsumeConfirmToken` or
`CreateOrganizationInvitation`: an agent may read and may propose, and a write it
performed itself would be a write nobody confirmed
([[domains/ai-confirm-tokens]]). The same file requires every vertical to
implement `OrganizationScoped` and `HasMiddleware`, which is how a new agent
inherits the tenancy and metering pipeline instead of quietly opting out of it.

## Writing one

```bash
php artisan make:action CreateThing
```

Constructor-inject collaborators as private promoted properties, keep the class
`final readonly` where it holds no state, and wrap multi-model writes in
`DB::transaction()`. `app/Actions/SyncPermissions.php` is the example to copy for
an action that reconciles a whole table rather than creating one record.

See also [[architecture/six-method-spine]] for where an action sits relative to a
resource adapter, and [[domains/http-layer]] for the request/response half.
