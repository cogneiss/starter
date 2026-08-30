---
title: Why the resource contract grew from six methods to thirteen
status: current
supersedes: []
code_refs:
    - app/Resources/ResourceContract.php
    - app/Resources/Definitions/UserResource.php
    - .ai/rules/resources.md
updated: 2026-08-31
---

# Why the resource contract grew from six methods to thirteen

`app/Resources/ResourceContract.php` started with six methods and now declares
thirteen. The rule that governed the first six is what let the other seven in:
**a method arrives with its consumer, or not at all.**

## The original six

| Method        | Answers                                       |
| ------------- | --------------------------------------------- |
| `key()`       | the stable string that names this resource    |
| `label()`     | what a human calls it                         |
| `model()`     | which Eloquent model it maps to               |
| `dataClass()` | which `app/Data` class carries it to the page |
| `policy()`    | which policy authorizes it                    |
| `url(Model)`  | the link to one record                        |

`url()` is the one that earned the pattern. Without it, every place that renders
a mixed list of records grows a `switch (result.type)` on the frontend, and each
new resource means editing that switch in every consumer. With it, the backend
hands over a URL and the frontend renders a link. `.ai/rules/resources.md`
requires building it from Wayfinder helpers rather than a string literal, so a
renamed route breaks the build instead of producing a dead link.

## The seven the UX layer added

Three consumers arrived at once — a scoped search endpoint, a server-driven list
kit and a CSV export — and all three needed the same facts about a resource:

| Method                | Answers                                                        |
| --------------------- | -------------------------------------------------------------- |
| `searchable()`        | which columns a query matches, dotted for a `belongsTo`        |
| `sortable()`          | which columns may be ordered by, first entry being the default |
| `filters()`           | which `ResourceFilter`s a list of this resource offers         |
| `columns()`           | which columns an export writes, in order                       |
| `recordLabel()`       | the one-line title of a record in a result list                |
| `recordDescription()` | the optional second line under it                              |
| `scopedQuery()`       | the base query, already narrowed to the acting organization    |

Each is an allowlist rather than a reflection of the table, and that is the
point. `sortable()` means no request can order by a column the screen never
shows. `columns()` may name the ability a field needs, so a CSV cannot hand out
something the table would have withheld. Declaring `filters()` here rather than
on the page means two screens listing the same resource cannot disagree about
what `f[status]=active` means.

`scopedQuery()` is the security-carrying one. It returns a builder already
narrowed to the organization, so a foreign record is **not reachable** rather
than fetched and then hidden — see [[architecture/fail-closed-scoping]].
`AiConfirmTokenResource` shows the shape when the model's own scope is not
enough: a confirmation is addressed to one person, so the acting user is part of
the where clause, matching what its policy allows, and no signed-in user means
no rows.

## What is still out

`actions()` / `actionSchemas()`, an `ApiExposable` REST surface, the resource
loom and the AI presentation manifest. Same reason as before: no consumer.
`ResourceRegistry` is the seam they attach to when one arrives. The list and the
reasoning are in [[decisions/resource-spine]] and [[decisions/not-included]].

So the rule survives its own precedent — "adding a method means a new consumer
exists, say what it is in the PR". Seven methods were added and three consumers
were named. The number was never sacred; the requirement to name the consumer is.

## Cost of one adapter

`php artisan app:make-resource <Name>` generates the file along with everything
around it, and `tests/Pest.php` judges every definition's `searchable()`,
`sortable()` and `recordLabel()` against the real schema, so an adapter naming a
column that does not exist fails the suite instead of erroring at request time.
Guard G5 fails CI for a model without an adapter at all — see
[[architecture/convention-guards]], [[domains/resources]] and
[[domains/ux-list-kit]].
