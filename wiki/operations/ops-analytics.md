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
updated: 2026-09-01
---

# The analytics seam

Product analytics without a hard dependency. With `POSTHOG_KEY` set,
`PostHogReporter` sends events to PostHog (`POSTHOG_HOST` for self-hosted);
blank, `NullAnalyticsReporter` swallows them. Same zero-key contract as
[[operations/ops-error-reporting]]: keyless boot, no stray network calls
from the suite.

## What gets tracked

Events are declared, not sprayed: an action opts in with the `#[Track]`
attribute, and `DispatchesAnalytics` sends the event after the action
succeeds. `#[NoTrack]` exists to make an exclusion explicit on something a
sweep might otherwise annotate. An event carries the event name, the
organization and anonymised actor identifiers — never emails, names, record
contents or anything a token could be reconstructed from.

## Do Not Track

`HonorDoNotTrack` reads the browser's DNT / Global Privacy Control signals
and suppresses tracking for that request, whatever the server keys say. The
user's choice wins over the operator's configuration.

Deleting an account ([[operations/ops-gdpr]]) leaves nothing to scrub here
precisely because identifiers were anonymised on the way out.
