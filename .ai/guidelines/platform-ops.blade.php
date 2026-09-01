# Platform ops guidelines

- The platform ops layer is `routes/api.php`, `app/Http/Controllers/Api`, `app/Webhooks`, `app/Admin`, `app/Support/Analytics`, `app/Support/Reporting`, `app/Support/Health` and their middleware. Read `.ai/rules/platform-ops.md` and load the `platform-ops` skill before editing any of it.
- Every API request resolves its organization from the token, never from a header, a route parameter or a request body. `EnsureTokenMatchesOrganization` enforces it; `tests/Feature/ApiTokenScopeTest.php` attacks it.
- Scope at the query level. A foreign id returns 404, not 403 — in the API and in the admin area, where `/admin` itself is a 404 without the `platform` ability.
- Tokens are shown once at creation and stored hashed. Never log a token, a webhook secret, a signature or a provider key; `tests/Feature/WebhookSecretLogCanaryTest.php` sweeps for a canary.
- The read API is registry-driven and read-only. A resource joins by registration; there is no write endpoint, and that absence is deliberate.
- Rate limits come from `config/api.php` and the organization's tier from the `api-rate-tier` Pennant feature. Usage rows come from `LogApiRequest` and are pruned by `api:prune-logs`.
- Webhook deliveries are signed with an HMAC of `{timestamp}.{body}`. `SsrfGuard` checks the destination at save time and again after DNS resolution at delivery time — the second check defeats rebinding; never remove it.
- Sentry and PostHog are seams: blank keys bind null implementations and the whole suite boots with an empty `.env`. `ZeroKeyBootTest` fails a change that makes anything require a key.
- `.env.example` keeps every provider key blank.
