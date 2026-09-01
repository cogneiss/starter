---
title: Feature flags
status: current
supersedes: []
code_refs:
    - app/Enums/KnownFeatures.php
    - app/Models/FeatureOverride.php
    - app/Console/Commands/ExpireFeatureOverridesCommand.php
    - config/features.php
    - config/pennant.php
    - tests/Feature/FeatureFlagTest.php
    - app/Ai/Agents/DashboardBriefing.php
updated: 2026-09-01
---

# Feature flags

Pennant, configured in `config/pennant.php`, with one addition: every flag is
declared in `app/Enums/KnownFeatures.php` before it is used.

## Why a registry in front of Pennant

A flag checked by string has one failure mode, and it is the bad one: a typo
resolves to "off". The feature is simply absent, in production, silently, and the
string looks correct in review. With the enum, a typo does not compile, and
`tests/Feature/FeatureFlagTest.php` covers the registry itself.

## Defaults

`config/features.php` sets the defaults:

| Flag                    | Env                             | Default |
| ----------------------- | ------------------------------- | ------- |
| `ai-briefing-enabled`   | `FEATURE_AI_BRIEFING_ENABLED`   | `false` |
| `impersonation-enabled` | `FEATURE_IMPERSONATION_ENABLED` | `false` |
| `social-login-enabled`  | `FEATURE_SOCIAL_LOGIN_ENABLED`  | `false` |

All three ship off. Impersonation is a support tool that reads another user's
data, and social login needs credentials that do not exist in a fresh clone —
neither should be on because nobody chose.

The AI briefing is off for a third reason: it spends money. `DashboardBriefing`
checks `KnownFeatures::AiBriefingEnabled->enabledFor($organization)` and returns
nothing when it is off, so the dashboard renders without it rather than failing.
A flag on the per-organization override is how one organization tries the
briefing while the rest of the tenancy does not pay for it
([[domains/ai-metering-and-quotas]]).

## Per-organization overrides

`app/Models/FeatureOverride.php` stores an override for one organization, with an
optional expiry. Expired rows are dropped by:

```bash
php artisan app:expire-feature-overrides
```

`FeatureOverride` has no resource adapter and is exempted in
`config/conventions.php` as _pending resource adapter_ — a temporary reason, and
it says so ([[architecture/convention-guards]]).

Not every registry entry is a boolean case. `KnownFeatures::API_RATE_TIER` is a
class constant instead, because that Pennant feature resolves to a string — an
organization's API rate tier — rather than a true/false switch; naming it
through the registry still keeps a typo from existing. Its default lives in
`config/api.php`, not `config/features.php`
([[operations/ops-usage-and-limits]]).

The two flagged features are described in [[features/organizations]]
(impersonation) and [[features/authentication]] (social login).
