---
title: Signed outgoing webhooks
status: current
supersedes: []
code_refs:
    - app/Webhooks/WebhookEvents.php
    - app/Webhooks/Signature.php
    - app/Webhooks/SsrfGuard.php
    - app/Webhooks/NativeHostnameResolver.php
    - app/Listeners/DispatchWebhookEvents.php
    - app/Jobs/SendWebhookDelivery.php
    - app/Models/WebhookEndpoint.php
    - app/Models/WebhookDelivery.php
    - config/webhooks.php
    - tests/Feature/WebhookSignatureTest.php
    - tests/Feature/WebhookSsrfTest.php
    - tests/Feature/WebhookDeliveryTest.php
updated: 2026-09-01
---

# Signed outgoing webhooks

An organization registers an endpoint; model events for registered resources
(the catalogue in `WebhookEvents` — every resource × created/updated/deleted)
become queued deliveries to it.

## Signature

Every request carries `X-Signature`, an HMAC-SHA256 of
`{timestamp}.{raw body}` keyed with the endpoint's secret, and `X-Timestamp`
to bound replays. The verification recipe receivers follow is in
`SECURITY.md`. The secret is stored encrypted, shown in settings, and never
logged — the delivery row's response snippet is scrubbed of both the secret
and the signature, and `tests/Feature/WebhookSecretLogCanaryTest.php` sweeps
the logs for a canary secret to prove it.

## SSRF guard

`SsrfGuard` refuses private, loopback, link-local and otherwise non-routable
destinations — at save time _and again at delivery time, after DNS
resolution_, which is what defeats rebinding: a hostname re-pointed at
`169.254.169.254` between save and send is refused and the delivery recorded
as blocked.

## Retry ladder

A failed attempt retries at 30, 120, 480 and 1920 seconds, five attempts
total, all from `config/webhooks.php`. Ten consecutive final failures
deactivate the endpoint and notify the owners; a delivery can be replayed
from settings as a fresh row. Failures also surface to super admins beside
the endpoints list — [[operations/ops-admin-area]].
