---
paths:
  - 'app/Admin/**'
  - 'app/Http/Controllers/Api/**'
  - 'app/Http/Middleware/**'
  - 'app/Support/Analytics/**'
  - 'app/Support/Health/**'
  - 'app/Support/Reporting/**'
  - 'app/Webhooks/**'
  - config/api.php
  - config/audit.php
  - config/webhooks.php
  - routes/api.php
---

# Admin Analytics Api Health Middleware Reporting Webhooks Config Routes

- Every API request resolves its organization from the token, never from a header, a route parameter or a request body. `EnsureTokenMatchesOrganization` enforces it; `tests/Feature/ApiTokenScopeTest.php` attacks it.
- The read API is registry-driven and read-only. A resource joins by registration; there is no write endpoint, and that absence is deliberate.
- Rate limits come from `config/api.php` and the organization's tier from the `api-rate-tier` Pennant feature. Usage rows come from `LogApiRequest` and are pruned by `api:prune-logs`.
- Webhook deliveries are signed with an HMAC of `{timestamp}.{body}`. `SsrfGuard` checks the destination at save time and again after DNS resolution at delivery time — the second check defeats rebinding; never remove it.
