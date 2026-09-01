---
name: platform-ops
description: "Use when touching the platform ops layer of this starter kit: the token-authenticated read API at /api/v1, per-organization API tokens, usage logging and rate tiers, the append-only audit log, GDPR export and anonymising deletion, the /health endpoint, the Sentry and PostHog seams, signed outgoing webhooks with the SSRF guard, the Content Security Policy and public-form friction, or the super-admin control plane at /admin. Covers token-resolved tenancy where a foreign id is a 404, the shown-once-stored-hashed token rule, the never-log-a-secret rule with its canary test, the double SSRF check after DNS resolution, zero-key boot for every provider seam, and the platform ability that 404s rather than 403s. The mistakes here leak tenants or secrets, so read it before editing."
license: MIT
metadata:
    author: cogneiss
---

# Platform ops

Everything below is trust-boundary code. The web app's rules (query-level
scoping, no client-side controls) apply here with tokens instead of sessions.

## The read API

`routes/api.php` serves `/api/v1`: the catalogue at `/`, then `{resource}`
index and `{resource}/{id}` show, all registry-driven. A resource joins the
API by registration, never by a bespoke controller. The surface is read-only
on purpose — there is no write endpoint to extend.

Four middleware own the pipeline, in order: `auth:sanctum`,
`EnsureTokenMatchesOrganization`, `LogApiRequest`, `ApplyRateTier`. The
organization is resolved from the token — never from a header, a route
parameter or a request body. A foreign id is a 404, not a 403.

## Tokens

`CreateApiToken` returns the plaintext exactly once; only the hash is stored.
Rotation is create-new, deploy, revoke-old. `tokens:prune` clears expired
rows. Never build a path that prints a stored token — none exists today.

## Usage and rate tiers

`LogApiRequest` records every request; `ApplyRateTier` reads limits from
`config/api.php`, with the organization's tier decided by the `api-rate-tier`
Pennant feature. Add a tier in config, not a column.

## Audit log, GDPR

`RecordModelActivity` writes append-only rows with secrets redacted. GDPR
export runs through `BuildGdprExport`; deletion anonymises through
`DeleteAccount`; `gdpr:purge` and the audit prune enforce retention from
config.

## Webhooks

Deliveries are signed (`X-Signature`, HMAC of `{timestamp}.{body}`) and the
secret is encrypted at rest and scrubbed from every stored response snippet.
`SsrfGuard` runs at save time and again at delivery time after DNS
resolution — the second check is what defeats rebinding; never remove it.
Retries, attempt counts and deactivation thresholds live in
`config/webhooks.php`.

## Seams

Sentry (`SENTRY_DSN`) and PostHog (`POSTHOG_KEY`) bind null implementations
when blank. Zero-key boot is a contract: app, pages and suite all run with an
empty `.env`.

## The admin area

`/admin` sits behind `EnsurePlatformAdmin` — the `platform` ability, granted
only to super admins, refusing with a 404. It reuses the list kit
cross-organization in exactly one controller; never widen
`withoutOrganizationScope()` beyond it.

## Before you call it done

```bash
herd php artisan test --compact --filter='ApiTokenScope|ApiResource|RateTier|WebhookSsrf|WebhookSecretLogCanary|AdminAccess|ZeroKeyBoot|Csp'
```

The reasoning behind each of these is in `wiki/operations/ops-api-tokens.md`,
`wiki/operations/ops-read-api.md`, `wiki/operations/ops-usage-and-limits.md`,
`wiki/operations/ops-audit-log.md`, `wiki/operations/ops-gdpr.md`,
`wiki/operations/ops-webhooks.md`, `wiki/operations/ops-csp.md` and
`wiki/operations/ops-admin-area.md`.
