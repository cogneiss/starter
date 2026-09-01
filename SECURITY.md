# Security

## Verifying webhook signatures

Every webhook request carries an `X-Signature` header — an HMAC-SHA256 of
`{timestamp}.{raw body}` keyed with your endpoint's signing secret — and an
`X-Timestamp` header with the Unix time the signature was computed. Verify
both before trusting a delivery: the signature proves the body was not
altered, the timestamp bounds how long a captured request can be replayed.

```php
function verifyWebhook(string $body, string $signature, int $timestamp, string $secret): bool
{
    if (abs(time() - $timestamp) > 300) {
        return false;
    }

    $expected = hash_hmac('sha256', $timestamp.'.'.$body, $secret);

    return hash_equals($expected, $signature);
}
```

Compute the HMAC over the exact raw request body — re-encoding the JSON will
change the bytes and fail verification.

## Tenant isolation

Every API request resolves its organization from the token that authenticated
it — never from a header, a route parameter or a request body. Scoping happens
at the query level, so a record belonging to another organization is
indistinguishable from a record that does not exist: both answer 404, never 403. The same rule gates the admin area — without the `platform` ability the
URL itself answers 404.

## Outgoing webhook SSRF guard

Webhook endpoint URLs are validated when saved _and_ re-checked at delivery
time, after DNS resolution: the resolved address is refused if it is private,
loopback, link-local or otherwise non-routable. Re-checking after resolution
is what defeats DNS rebinding — a hostname that pointed somewhere public at
save time and somewhere internal at delivery time is refused, and the delivery
row records it as blocked.

## Content Security Policy

Every HTML response carries an enforcing CSP with per-request nonces — no
`unsafe-inline` script or style. A script injected into page content does not
execute; it fails the policy.

## GDPR

A user can export their personal data (queued, delivered as a download) and
delete their account. Deletion anonymises rather than shreds audit history,
and `php artisan gdpr:purge` hard-deletes anonymised accounts once the
retention window passes.

## Reporting a vulnerability

Email the maintainers privately rather than opening a public issue. Do not
include working exploit payloads in the first message.
