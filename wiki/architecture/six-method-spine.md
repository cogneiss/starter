---
title: Why the resource contract has six methods
status: current
supersedes: []
code_refs:
    - app/Resources/ResourceContract.php
    - app/Resources/Definitions/UserResource.php
    - .ai/rules/resources.md
updated: 2026-08-24
---

# Why the resource contract has six methods

`app/Resources/ResourceContract.php` declares six methods and no more:

| Method        | Answers                                       |
| ------------- | --------------------------------------------- |
| `key()`       | the stable string that names this resource    |
| `label()`     | what a human calls it                         |
| `model()`     | which Eloquent model it maps to               |
| `dataClass()` | which `app/Data` class carries it to the page |
| `policy()`    | which policy authorizes it                    |
| `url(Model)`  | the link to one record                        |

## The method that earns the pattern

`url()`. Without it, every place that renders a mixed list of records grows a
`switch (result.type)` on the frontend, and each new resource means editing that
switch in every consumer. With it, the backend hands over a URL and the frontend
renders a link. `.ai/rules/resources.md` requires building it from Wayfinder
helpers rather than a string literal, so a renamed route breaks the build instead
of producing a dead link.

## Why not the usual seven or twelve

The methods the pattern normally carries — `searchQuery()`, `visibleTo()`,
`actions()`, API exposure — each exist to serve a consumer: a search index, an
assistant layer, a REST surface. None of those ship here, and an adapter method
with no consumer is a guess that ages badly. `ResourceRegistry` is the seam they
attach to when a consumer arrives. The full list of what was skipped and why is
in [[decisions/resource-spine]].

This is why the rule is "adding a seventh method means a new consumer exists —
say what it is in the PR". The number is not sacred; the requirement to name the
consumer is.

## Cost of one adapter

Six methods, all mechanical, and `php artisan app:make-resource <Name>`
generates the file along with everything around it. Guard G5 fails CI for a model
without one, so the adapter is not optional — see
[[architecture/convention-guards]] and [[domains/resources]].
