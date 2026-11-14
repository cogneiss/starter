---
title: AI metering and quotas
status: current
supersedes: []
code_refs:
    - app/Ai/Middleware/RecordAudit.php
    - app/Ai/Middleware/EnforceQuota.php
    - app/Support/AiQuota.php
    - app/Support/AiPricing.php
    - app/Support/AiTier.php
    - config/ai.php
    - database/migrations/2026_11_14_173854_add_run_id_tool_arguments_and_error_to_ai_audit_logs_table.php
    - tests/Feature/Ai/AiPricingTest.php
    - tests/Feature/Ai/AiAuditTest.php
    - app/Models/AiAuditLog.php
    - app/Models/AiCreditLedgerEntry.php
    - app/Actions/SummarizeAiUsage.php
    - app/Console/Commands/AiUsageCommand.php
    - tests/Feature/Ai/AiQuotaTest.php
    - tests/Feature/Console/AiUsageCommandTest.php
updated: 2026-11-15
---

# AI metering and quotas

Every agent run is recorded, priced and counted. The same rows answer three
questions: what happened, what it cost, and whether the next request is allowed.

## The record

`app/Ai/Middleware/RecordAudit.php` writes an `AiAuditLog` row for each model step:
organization, user, agent, provider, model, tier, token counts, cost in micros,
duration and status. It debits the credit ledger in the same transaction. It
runs last in the pipeline, so the record describes what actually went out.
Middleware wraps every generation step and each step is a billed round trip, so
a run that calls tools writes one row per step. The steps of one run share a
`run_id`, the SDK invocation id, which also survives a failover to a second
provider. The row is written from the `StepResult->then()` callback with the
step's `StepResponse`; token counts come from `inputTokens` and `outputTokens`
(stored in `prompt_tokens` and `completion_tokens`), and `total_tokens` from
`totalTokens()`.

Tool calls are stored by name in `tool_calls` and with their arguments in
`tool_arguments`. Arguments are scrubbed first: keys listed in `audit.redact`, or
named like a key, token, secret, password or authorization header, become
`[redacted]`, and long strings are cut. An oversize set is replaced by a
truncation marker.

Refusals are recorded with status `Blocked` and the reason. A prompt that throws
is recorded with status `Error` and a capped `error` text, then the exception
propagates. A streamed step that fails is not audited, because streams are metered
only when they complete.

Cost comes from `app/Support/AiPricing.php`, which reads `ai.pricing` keyed by
provider and model and prices `inputTokens` and `outputTokens` per million. A model
with no entry, or no provider, is priced at `ai.pricing_fallback`, set at the
dearest listed rate. Unpriced runs therefore over-count against the budget rather
than costing nothing. `tests/Feature/Ai/AiPricingTest.php` walks every tier and
failover model and fails when one has no price, and `php artisan app:doctor` also
reports an unpriced configured model. The failover prices in `config/ai.php` are
placeholders at the ceiling rate and are not verified.

## Failover

`app/Support/AiTier.php` returns a provider map, primary then failover, only when
the tier names a failover provider and both providers carry a key. With one key it
returns the single provider, so a blank-key boot works. The SDK moves to the second
provider when the first fails in a way it treats as failoverable.

## The limits

`app/Support/AiQuota.php` measures three, all from the audit log:

| Limit                             | Config key                              | Default       |
| --------------------------------- | --------------------------------------- | ------------- |
| requests per member per hour      | `ai.quotas.user_requests_per_hour`      | 60            |
| requests per organization per day | `ai.quotas.org_requests_per_day`        | 2000          |
| spend per organization per month  | `ai.quotas.org_budget_micros_per_month` | $50 in micros |

Counting reads the log rather than a counter, so a limit cannot be reset by
anything short of time passing. The request limits count distinct run ids, so a
run with five tool steps is one request. Rows with no run id count one each.
Blocked requests are not counted: refusing one costs nothing, so it must not push
the member further over. Error rows are counted, because the attempt reached a
provider.

`app/Ai/Middleware/EnforceQuota.php` runs the check first in the pipeline, inside
the agent's organization, and refuses before a prompt reaches a provider. It
checks only the first step (`isFirstStep()`): a run that was let in finishes its
tool loop rather than dying halfway.

## Reading it back

`app/Actions/SummarizeAiUsage.php` is the one reporting query — totals plus a
breakdown by agent and by tier, dearest first. Two callers share it so a terminal
and a screen cannot quote different numbers:

- `php artisan ai:usage` — `--org` by id or slug, `--since` for the window,
  `--json` for the whole report as JSON.
- `resources/js/pages/organization/ai-usage.tsx` — the organization's own last
  thirty days, drawn with [[domains/ai-blocks]].

The command deliberately reads across organizations: the console has nothing
bound and naming one is what `--org` is for. The page always passes the bound
organization, and `tests/Feature/Controllers/AiUsagePageTest.php` proves another
organization's rows are never counted there. That is the sanctioned exception
described in [[architecture/fail-closed-scoping]].
