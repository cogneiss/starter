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
