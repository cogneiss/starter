---
title: The analytics seam
status: current
supersedes: []
code_refs:
    - app/Support/Analytics/DispatchesAnalytics.php
    - app/Support/Analytics/PostHogReporter.php
    - app/Support/Analytics/NullAnalyticsReporter.php
    - app/Support/Analytics/Attributes/Track.php
    - app/Support/Analytics/Attributes/NoTrack.php
    - app/Http/Middleware/HonorDoNotTrack.php
    - config/services.php
    - tests/Feature/AnalyticsTest.php
    - app/Contracts/AnalyticsReporter.php
    - app/Jobs/SendAnalyticsEvent.php
    - app/Listeners/RecordAnalyticsEvent.php
updated: 2026-11-19
---

# The analytics seam

Product analytics without a hard dependency. With `POSTHOG_KEY` set,
`PostHogReporter` sends events to PostHog (`POSTHOG_HOST` for self-hosted);
blank, `NullAnalyticsReporter` swallows them. Same zero-key contract as
[[operations/ops-error-reporting]]: keyless boot, no stray network calls
from the suite.

## What gets tracked

Tracking is automatic for organization-scoped models and opt-in beyond the
default. `RecordAnalyticsEvent` (`app/Listeners/RecordAnalyticsEvent.php`) is a
wildcard Eloquent listener registered in `AppServiceProvider`. For any model
using `BelongsToOrganization` it tracks `created` and `deleted` with no
per-model wiring. `#[Track(['updated'])]` on a model adds more events and
`#[NoTrack]` on a model opts it out entirely. Both attributes target classes, not
actions. An event is named `<snake_model>.<action>` and carries the model name,
key and organization id; an `updated` event adds the changed attribute names,
never a value (`AnalyticsReporter`'s docblock states the same rule:
identifiers only, never an attribute value).

`App\Contracts\AnalyticsReporter` is the provider contract: `identify`, `group`,
`track` and `reset`. `PostHogReporter` turns each call into a queued
`SendAnalyticsEvent`, the one HTTP POST to `{host}/capture/`, so a slow or down
provider never blocks a response.

## Do Not Track

Server side, `DispatchesAnalytics` (used by the reporters) checks the request's
`DNT` header and sends nothing when it is `1`, whatever driver is bound.
Global Privacy Control is not read by either file. `HonorDoNotTrack`
only shares the same `DNT: 1` signal with the front end as the Inertia prop
`doNotTrack`; its docblock says the application ships the signal, not front-end
analytics.

Deleting an account ([[operations/ops-gdpr]]) leaves nothing to scrub here
precisely because identifiers were anonymised on the way out.
