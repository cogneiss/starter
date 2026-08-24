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
updated: 2026-08-24
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
| `impersonation-enabled` | `FEATURE_IMPERSONATION_ENABLED` | `false` |
| `social-login-enabled`  | `FEATURE_SOCIAL_LOGIN_ENABLED`  | `false` |

Both ship off. Impersonation is a support tool that reads another user's data, and
social login needs credentials that do not exist in a fresh clone — neither
should be on because nobody chose.

## Per-organization overrides

`app/Models/FeatureOverride.php` stores an override for one organization, with an
optional expiry. Expired rows are dropped by:

```bash
php artisan app:expire-feature-overrides
```

`FeatureOverride` has no resource adapter and is exempted in
`config/conventions.php` as _pending resource adapter_ — a temporary reason, and
it says so ([[architecture/convention-guards]]).

The two flagged features are described in [[features/organizations]]
(impersonation) and [[features/authentication]] (social login).
