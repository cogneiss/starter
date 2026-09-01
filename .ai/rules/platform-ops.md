---
paths:
    - "routes/api.php"
    - "app/Http/Controllers/Api/**"
    - "app/Http/Middleware/EnsureTokenMatchesOrganization.php"
    - "app/Http/Middleware/ApplyRateTier.php"
    - "app/Http/Middleware/LogApiRequest.php"
    - "app/Http/Middleware/EnsurePlatformAdmin.php"
    - "app/Http/Middleware/SetContentSecurityPolicy.php"
    - "app/Http/Middleware/ProtectPublicForm.php"
    - "app/Webhooks/**"
    - "app/Admin/**"
    - "app/Support/Analytics/**"
    - "app/Support/Reporting/**"
    - "app/Support/Health/**"
    - "config/api.php"
    - "config/webhooks.php"
    - "config/audit.php"
---

# The platform ops layer

## The token is the organization

Every `/api/v1` request resolves its organization from the presented token —
never from a header, a route parameter or a request body.
`EnsureTokenMatchesOrganization` enforces it and
`tests/Feature/ApiTokenScopeTest.php` attacks it. Tokens are shown once at
creation and stored hashed; there is no code path that can print one back.

## A foreign id is a 404

API reads scope at the query level, exactly like the web list kit. A record
from another organization does not 403 — it does not exist. Same rule in the
admin area: without the `platform` ability, `/admin` itself is a 404.

## The read API is registry-driven and read-only

`routes/api.php` exposes the resource catalogue; a new resource joins the API
by registration, not by a new controller. There is no write endpoint —
that absence is deliberate (`FEATURES.md`, "Not included").

## Rate tiers and usage live in config and Pennant

Limits come from `config/api.php`; an organization's tier comes from the
`api-rate-tier` Pennant feature, never from a column you invent. Every request
is logged by `LogApiRequest` and pruned by `api:prune-logs`.

## Never log a secret

No token, webhook secret, signature or provider key reaches a log, an audit
row, an error report or an analytics event.
`tests/Feature/WebhookSecretLogCanaryTest.php` sweeps for a canary; keep it
passing, never widen its exclusions.

## Webhooks are signed and SSRF-guarded twice

Deliveries carry an HMAC of `{timestamp}.{body}`. `SsrfGuard` refuses
non-routable destinations at save time and again at delivery time after DNS
resolution — the second check defeats rebinding, so never "optimize" it away.
Retry ladder and deactivation thresholds come from `config/webhooks.php`.

## Seams stay silent without keys

Sentry and PostHog bind their null implementations when their keys are blank.
The app, the pages and the whole suite boot with an empty `.env`; a change
that makes any of them require a key fails `ZeroKeyBootTest`.

The long versions live in `wiki/operations/ops-*.md`.
