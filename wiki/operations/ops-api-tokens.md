---
title: Per-organization API tokens
status: current
supersedes: []
code_refs:
    - app/Actions/CreateApiToken.php
    - app/Actions/RevokeApiToken.php
    - app/Models/ApiToken.php
    - app/Http/Controllers/OrganizationApiTokenController.php
    - app/Http/Requests/StoreApiTokenRequest.php
    - app/Policies/ApiTokenPolicy.php
    - app/Console/Commands/PruneApiTokensCommand.php
    - tests/Feature/ApiTokenTest.php
updated: 2026-09-01
---

# Per-organization API tokens

A token belongs to an organization, not to a user. Whoever holds it acts as
that organization on the read API — see [[operations/ops-read-api]] — and the
organization is resolved from the token itself, never from a header, a route
parameter or a request body.

## Creation and the one showing

`App\Actions\CreateApiToken` creates the token through Sanctum with the
organization pinned on the row. The plaintext value is returned exactly once,
by the create response; the database keeps only the hash. There is no
"reveal" endpoint to add later — losing the value means creating a
replacement, which is also the rotation story: create the new token, deploy
it, revoke the old one through `App\Actions\RevokeApiToken`.

## Abilities and expiry

A token carries a list of scoped abilities (`read:users` and friends) and an
optional expiry. `StoreApiTokenRequest` validates both; `ApiTokenPolicy`
decides who in the organization may manage tokens at all.

## Retention

`php artisan tokens:prune` deletes revoked and expired tokens once they age
past the retention window in `config/api.php`. Until then they stay visible
in settings, so an operator can see what was revoked and when.

Usage produced by these tokens is logged and rate-limited — see
[[operations/ops-usage-and-limits]] — and every token row also appears in the
admin area, hashed, never plaintext ([[operations/ops-admin-area]]).
