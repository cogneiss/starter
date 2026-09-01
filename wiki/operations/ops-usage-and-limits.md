---
title: API usage logging and rate tiers
status: current
supersedes: []
code_refs:
    - app/Http/Middleware/LogApiRequest.php
    - app/Http/Middleware/ApplyRateTier.php
    - app/Models/ApiRequestLog.php
    - app/Actions/SummarizeApiUsage.php
    - app/Http/Controllers/OrganizationApiUsageController.php
    - app/Console/Commands/PruneApiRequestLogsCommand.php
    - config/api.php
    - tests/Feature/ApiUsageTest.php
    - tests/Feature/RateTierTest.php
updated: 2026-09-01
---

# API usage logging and rate tiers

Every request to the read API ([[operations/ops-read-api]]) writes one row:
which token, which organization, which route, the status and the duration.
Never the token value and never the response body.

## Rate tiers

Limits live in `config/api.php` under `rate_tiers`, per plan tier. An
organization's tier comes from the `api-rate-tier` Pennant feature; with no
override the `default` tier applies. Each tier carries two limits — per token
and per organization — and both are enforced, so a tenant cannot dodge its
organization ceiling by minting more tokens. `ApplyRateTier` answers 429 with
the standard rate-limit headers when either is exceeded.

## The usage dashboard

The usage page in organization settings reads the same log rows through
`SummarizeApiUsage` — requests over time, top routes, and how close the
organization is to its tier. There is no second bookkeeping to drift from the
truth.

## Retention

`php artisan api:prune-logs` deletes rows older than the retention window in
`config/api.php`. The window is config, not code, because how long usage data
is kept is a policy decision, and it interacts with GDPR deletion —
[[operations/ops-gdpr]].
