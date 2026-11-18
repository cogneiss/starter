---
title: The error reporting seam
status: current
supersedes: []
code_refs:
    - app/Contracts/ErrorReporter.php
    - app/Support/Reporting/SentryErrorReporter.php
    - app/Support/Reporting/NullErrorReporter.php
    - config/services.php
    - .env.example
    - vendor/sentry/sentry-laravel/config/sentry.php
    - vendor/sentry/sentry-laravel/src/Sentry/Laravel/Features/AiIntegration.php
    - vendor/sentry/sentry-laravel/src/Sentry/Laravel/Features/Feature.php
    - app/Ai/Middleware/RecordAudit.php
    - tests/Feature/ErrorReporterTest.php
    - app/Support/Reporting/ReportsErrors.php
updated: 2026-11-18
---

# The error reporting seam

The application reports through `App\Contracts\ErrorReporter`, an interface
with two implementations. With `SENTRY_DSN` set, `SentryErrorReporter` sends
exceptions to Sentry with the release from `SENTRY_RELEASE`; with the key
blank, `NullErrorReporter` is bound and every report is a no-op.

## The shared half

`ReportsErrors` (`app/Support/Reporting/ReportsErrors.php`) is the trait both
reporters use; only `send()` differs. `report()` mints one reference UUID per
throwable, remembers it in a `WeakMap` so `reference()` can repeat it in the 500
payload, and passes a context of identifiers only: organization id, user id, a
per-request id shared by every error in that request, and the release. Request
body, headers and tokens are left out on purpose.

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

## AI tracing

`sentry/sentry-laravel` ships gen_ai spans for laravel/ai calls (invoke_agent,
chat, execute_tool, embeddings), each on by default under `tracing` in
`vendor/sentry/sentry-laravel/config/sentry.php`. They exist only when
`traces_sample_rate` is above zero, and it reads `SENTRY_TRACES_SAMPLE_RATE`,
which is blank in `.env.example`; tracing then also covers request, database
and queue spans. Model, token counts and agent name are always attached.
Prompts, instructions and responses are attached only when `send_default_pii`
is on (`Features/Feature.php`, `Features/AiIntegration.php`), and it defaults
to off, so tenant content stays out. Per-organization metering in
`ai_audit_logs` (`app/Ai/Middleware/RecordAudit.php`) stays the source of truth
for cost, one row per model step: a replayed step collides on the unique
`(run_id, step_number)` and is not metered twice; the spans are for latency and debugging.

The analytics seam is the same shape — [[operations/ops-analytics]].
