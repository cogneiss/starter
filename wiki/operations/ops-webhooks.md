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
    - app/Actions/CreateWebhookEndpoint.php
    - app/Actions/ReplayWebhookDelivery.php
    - app/Data/WebhookDeliveryData.php
    - app/Data/WebhookEndpointData.php
    - app/Http/Controllers/OrganizationWebhookEndpointController.php
    - app/Http/Controllers/WebhookDeliveryReplayController.php
    - app/Http/Requests/StoreWebhookEndpointRequest.php
    - app/Http/Requests/UpdateWebhookEndpointRequest.php
    - app/Notifications/WebhookEndpointDeactivated.php
    - app/Rules/PublicHttpsUrl.php
    - app/Webhooks/ResolvesHostnames.php
updated: 2026-11-18
---

# Signed outgoing webhooks

An organization registers an endpoint; model events for registered resources
(the catalogue in `WebhookEvents` — every resource × created/updated/deleted)
become queued deliveries to it.

## Signature

Every request carries `X-Signature`, an HMAC-SHA256 of
`{timestamp}.{raw body}` keyed with the endpoint's secret, and `X-Timestamp`
to bound replays. The verification recipe receivers follow is in
`SECURITY.md`. The secret is stored encrypted, shown once and never
logged — the delivery row's response snippet is scrubbed of both the secret
and the signature, and `tests/Feature/WebhookSecretLogCanaryTest.php` sweeps
the logs for a canary secret to prove it.

## Endpoints in settings

`OrganizationWebhookEndpointController` is the settings screen at
`settings/webhooks`: it lists the endpoints and the last 50 deliveries, and
creates, updates and deletes endpoints, each behind a policy check.
`CreateWebhookEndpoint` generates the `whsec_` secret and returns it next to
the endpoint; the controller flashes it once and no later response carries it.
The organization is not a parameter anywhere: it comes from the bound
`OrganizationContext`, so a request cannot name one. `StoreWebhookEndpointRequest`
and `UpdateWebhookEndpointRequest` validate the URL with `PublicHttpsUrl` and
restrict `events` to the keys in `WebhookEvents`; the update request also
accepts `active`, which is how an operator turns a deactivated endpoint back on.
`WebhookEndpointData` and `WebhookDeliveryData` are the list rows. Neither
holds the secret or the payload.

`ReplayWebhookDelivery`, reached through `WebhookDeliveryReplayController`
(policy ability `replay`), creates a new pending delivery with the same event
and payload and queues `SendWebhookDelivery` for it after commit. The original
row keeps its attempts and outcome.

## SSRF guard

`SsrfGuard` refuses private, loopback, link-local and otherwise non-routable
destinations — at save time _and again at delivery time, after DNS
resolution_, which is what defeats rebinding: a hostname re-pointed at
`169.254.169.254` between save and send is refused and the delivery recorded
as blocked. `PublicHttpsUrl` is the save-time half: a validation rule that wraps
`SsrfGuard::allows()`. `ResolvesHostnames` is the DNS seam the guard resolves
through (`NativeHostnameResolver` is bound to it in `AppServiceProvider`), so a
test can bind a resolver that answers differently at validation and at send.

## Retry ladder

A failed attempt retries at 30, 120, 480 and 1920 seconds, five attempts
total, all from `config/webhooks.php`. Ten consecutive final failures
deactivate the endpoint and notify the owners with `WebhookEndpointDeactivated`, an in-app (database) notification linking to the webhook settings; a delivery can be replayed
from settings as a fresh row. Failures also surface to super admins beside
the endpoints list — [[operations/ops-admin-area]].
