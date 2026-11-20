---
paths:
  - 'app/**'
---

# App

## Organization scoping is fail-closed — never filter by organization_id by hand
Organization-scoped models use `App\Concerns\BelongsToOrganization`, which adds the global scope and fills `organization_id`. Never write a manual `where('organization_id', ...)`; never bypass the scope except through `withoutOrganizationScope()`, and leave a comment saying why when you do.

Jobs that touch scoped models implement `App\Contracts\OrganizationAware` and return `WithOrganizationContext` from `middleware()`, otherwise the queue runs them with whatever organization the previous job left bound.

Tests that need a bound organization use `OrganizationContext::runAs()`. With nothing bound, a scoped query throws `OrganizationContextMissing` (`ORGANIZATIONS_STRICT=true`).

The user-facing word is always "organization", never "tenant".

## An email address is lower-cased before it is compared
Stored addresses are lower case (`.ai/rules/database.md`). An address that comes from outside, such as a sign-in form, an OAuth identity, a console argument or an environment variable, goes through `Str::lower()` once, before the first query that compares it. PostgreSQL and SQLite compare by case, so `Ada@Example.com` finds no row there.

A form that stores an address keeps the `lowercase` rule beside `unique`. The `unique` rule compares what was typed, and without `lowercase` the model would then store something else.
