---
title: The error reporting seam
status: current
supersedes: []
code_refs:
    - app/Contracts/ErrorReporter.php
    - app/Support/Reporting/SentryErrorReporter.php
    - app/Support/Reporting/NullErrorReporter.php
    - config/services.php
    - tests/Feature/ErrorReporterTest.php
updated: 2026-09-01
---

# The error reporting seam

The application reports through `App\Contracts\ErrorReporter`, an interface
with two implementations. With `SENTRY_DSN` set, `SentryErrorReporter` sends
exceptions to Sentry with the release from `SENTRY_RELEASE`; with the key
blank, `NullErrorReporter` is bound and every report is a no-op.

## Why a seam and not the SDK

Three reasons, all of them the same reason the AI layer boots keyless:

- **Zero-key boot** — the app, every page and the whole suite work with an
  empty `.env`. There is no "configure Sentry to run the tests" step.
- **No stray network calls** — the suite binds the null reporter, so a test
  can never post an exception to a real project.
- **Swappable** — Bugsnag or Rollbar is one new class implementing the same
  two methods, not a find-and-replace across the codebase.

What is reported is the exception and its context — never a token, a webhook
secret or a provider key; the same redaction rules as the audit log
([[operations/ops-audit-log]]) apply to anything attached as context.

The analytics seam is the same shape — [[operations/ops-analytics]].
