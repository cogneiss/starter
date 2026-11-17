- The platform ops layer is `routes/api.php`, `app/Http/Controllers/Api`, `app/Webhooks`, `app/Admin`, `app/Support/Analytics`, `app/Support/Reporting`, `app/Support/Health` and their middleware. Read `.ai/rules/platform-ops.md` and load the `platform-ops` skill before editing.
- Never log a token, a webhook secret, a signature or a provider key. Scope at the query level: a foreign id is a 404.

@scoped(['routes/api.php', 'app/Http/Controllers/Api/**', 'app/Http/Middleware/**', 'app/Webhooks/**', 'app/Admin/**', 'app/Support/Analytics/**', 'app/Support/Reporting/**', 'app/Support/Health/**', 'config/api.php', 'config/webhooks.php', 'config/audit.php'])
- Every API request resolves its organization from the token, never from a header, a route parameter or a request body. `EnsureTokenMatchesOrganization` enforces it; `tests/Feature/ApiTokenScopeTest.php` attacks it.
- The read API is registry-driven and read-only. A resource joins by registration; there is no write endpoint, and that absence is deliberate.
- Rate limits come from `config/api.php` and the organization's tier from the `api-rate-tier` Pennant feature. Usage rows come from `LogApiRequest` and are pruned by `api:prune-logs`.
- Webhook deliveries are signed with an HMAC of `{timestamp}.{body}`. `SsrfGuard` checks the destination at save time and again after DNS resolution at delivery time — the second check defeats rebinding; never remove it.
@endscoped
