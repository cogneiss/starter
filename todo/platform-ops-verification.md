# Platform ops verification results

Recorded 2026-09-01, on `main`, after the full check battery. Each proof pairs
the exact command with the output it produced on this machine. All eight are
green; the full-suite coverage gate ran separately and is noted at the end.

## Proof 1 — token scoping

Every API request resolves its organization from the token. A token minted in
one organization cannot read another's records, whatever headers, route
parameters or body fields the request carries.

```bash
herd php artisan test --compact --filter='ApiTokenScopeTest'
```

```json
{
    "tool": "pest",
    "result": "passed",
    "tests": 8,
    "passed": 8,
    "assertions": 31,
    "duration_ms": 3469
}
```

## Proof 2 — 404 not 403

A foreign id is resolved inside the organization's own scoped query, so it does
not exist as far as the response is concerned: 404, never 403, in the read API
and its catalogue.

```bash
herd php artisan test --compact --filter='ApiResourceTest|ApiCatalogueTest'
```

```json
{
    "tool": "pest",
    "result": "passed",
    "tests": 5,
    "passed": 5,
    "assertions": 42,
    "duration_ms": 1222
}
```

## Proof 3 — anonymisation sweep

GDPR deletion anonymises what must survive (audit rows) and removes what must
not, and the sweep proves no personal field remains reachable afterwards.

```bash
herd php artisan test --compact --filter='GdprDeleteTest'
```

```json
{
    "tool": "pest",
    "result": "passed",
    "tests": 4,
    "passed": 4,
    "assertions": 27,
    "duration_ms": 1013
}
```

## Proof 4 — webhook signature and SSRF

Deliveries carry an HMAC of `{timestamp}.{body}`, and `SsrfGuard` checks the
destination at save time and again after DNS resolution at delivery time — the
second check is what defeats rebinding.

```bash
herd php artisan test --compact --filter='WebhookSignatureTest|WebhookSsrfTest'
```

```json
{
    "tool": "pest",
    "result": "passed",
    "tests": 16,
    "passed": 16,
    "assertions": 63,
    "duration_ms": 1507
}
```

## Proof 5 — no secret in logs

A canary webhook secret (`whsec_` prefixed) flows through a real delivery, then
the test sweeps the log output for it. A second sweep over the test output and
`storage/logs/` found zero occurrences.

```bash
herd php artisan test --compact --filter='WebhookSecretLogCanaryTest'
rg -c 'whsec_canary_2f9c1e' .work/tmp/canary-run.txt storage/logs/*.log; echo "sweep exit: $?"
```

```text
{"tool":"pest","result":"passed","tests":1,"passed":1,"assertions":6,"duration_ms":1242}
sweep exit: 1   (rg exit 1 = zero matches anywhere)
```

## Proof 6 — zero-key boot

With every provider key blank the application boots, binds the null Sentry and
PostHog implementations, and the suite runs — no feature quietly requires a key.

```bash
herd php artisan test --compact --filter='ZeroKeyBootTest'
```

```json
{
    "tool": "pest",
    "result": "passed",
    "tests": 4,
    "passed": 4,
    "assertions": 9,
    "duration_ms": 1854
}
```

## Proof 7 — CSP enforcing

The Content-Security-Policy header is emitted in enforcing mode (not
report-only) with a per-request nonce, on both web and error responses.

```bash
herd php artisan test --compact --filter='CspTest'
```

```json
{
    "tool": "pest",
    "result": "passed",
    "tests": 10,
    "passed": 10,
    "assertions": 34,
    "duration_ms": 7990
}
```

## Proof 8 — platform ability not grantable

`/admin` is a 404 without the `platform` ability, and no tenant-reachable path —
token creation included — can grant that ability. It exists only on the
super-admin session.

```bash
herd php artisan test --compact --filter='AdminAccessTest'
```

```json
{
    "tool": "pest",
    "result": "passed",
    "tests": 4,
    "passed": 4,
    "assertions": 20,
    "duration_ms": 872
}
```

## Full-suite gate

`composer test` (the phase 12 deciding command) and the line-coverage gate
(`herd coverage vendor/bin/pest --parallel --processes=4 --no-tia --coverage --exactly=100.0`)
both ran green on the same tree; the coverage total was exactly 100.0
(1790 tests, 1790 passed, `composer test exit: 0`).

Two environmental notes, not gate changes: Herd's CLI `php` ships without
xdebug, so `composer test` only finds a coverage driver with
`HERD_PHP_85_INI_SCAN_DIR` pointing at an ini dir that loads Herd's own
xdebug extension; and the browser suite's assertion timeout was raised from
the 5s default to 30s in `tests/Pest.php`, because coverage instrumentation
slowed requests enough that a handful of Playwright assertions flaked on
timing while passing in isolation. Assertions poll, so the longer window
weakens nothing.
