---
title: The registry-driven read API
status: current
supersedes: []
code_refs:
    - routes/api.php
    - app/Http/Controllers/Api/CatalogueController.php
    - app/Http/Controllers/Api/ResourceController.php
    - app/Http/Middleware/EnsureTokenMatchesOrganization.php
    - tests/Feature/ApiResourceTest.php
    - tests/Feature/ApiCatalogueTest.php
updated: 2026-09-01
---

# The registry-driven read API

`/api/v1` is a read-only JSON surface over the resource spine. It exposes
exactly the resources the registry registers — nothing is listed by hand, so a
new `app:make-resource` model appears on the API the moment its adapter
exists, and nothing else ever does.

## Routes

Three routes, all behind `auth:sanctum`,
`EnsureTokenMatchesOrganization`, request logging and the rate tier:

- `GET /api/v1` — the catalogue: which resources exist and what the token may
  read.
- `GET /api/v1/{resource}` — a paginated index.
- `GET /api/v1/{resource}/{id}` — one record.

## Scoping

The organization comes from the token ([[operations/ops-api-tokens]]), and the
query is scoped before it runs. A foreign id — a record that belongs to
another organization — answers 404, indistinguishable from an id that never
existed. There is no 403 to enumerate against.

Reads are the whole surface on purpose. Writes stay in the application, where
policies, Precognition and the confirm-token flow already govern them; a
write API would be a second copy of all three.

Every request lands in the usage log — [[operations/ops-usage-and-limits]].
