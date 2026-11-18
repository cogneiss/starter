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
    - database/migrations/2026_11_17_204024_add_cache_tokens_and_step_number_to_ai_audit_logs_table.php
    - tests/Feature/Ai/AiPricingTest.php
    - tests/Feature/Ai/AiAuditTest.php
    - app/Models/AiAuditLog.php
    - app/Models/AiCreditLedgerEntry.php
    - app/Actions/SummarizeAiUsage.php
    - app/Console/Commands/AiUsageCommand.php
    - tests/Feature/Ai/AiQuotaTest.php
    - tests/Feature/Console/AiUsageCommandTest.php
    - app/Actions/DebitAiCredits.php
    - app/Actions/GrantAiCredits.php
    - app/Data/AiUsageData.php
    - app/Data/AiUsageRowData.php
    - app/Enums/AiAuditStatus.php
    - app/Exceptions/AiQuotaExceededException.php
    - app/Http/Controllers/OrganizationAiUsageController.php
updated: 2026-11-18
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
provider and model and prices `inputTokens` and `outputTokens` per million.
`AiPricing::listed()` reads the price by array key and never by dot notation,
because a model name such as `gpt-6.1-terra` contains a dot and a dotted config
lookup would bill the fallback instead. A model with no entry, or no provider,
is priced at `ai.pricing_fallback`, set at the dearest listed rate. Unpriced runs therefore over-count against the budget rather
than costing nothing.

A provider may return a dated snapshot id such as `claude-sonnet-5-5-20261001`.
`listed()` tries the exact id first. On a miss it strips one trailing suffix
matching `-YYYYMMDD` or `-YYYY-MM-DD` and retries once, so the snapshot bills the
listed model's price. Prefix matching is not used: `claude-sonnet-5-5-fast` stays
unlisted and bills the ceiling. A price row without an integer `input` and
`output` is also treated as unlisted, so a malformed config row bills the ceiling
instead of throwing inside `RecordAudit` after the provider call was paid for.
`tests/Feature/Ai/AiPricingTest.php` and `tests/Feature/Ai/AiAuditTest.php`
cover both rules.

Cache tokens. Both gateways report `inputTokens` as the whole prompt with the
cache buckets inside it (`Laravel\Ai\Responses\Data\TextUsage::uncachedInputTokens()`),
so `AiPricing::costMicros()` carves cache reads and writes out of the input
rather than adding them: the uncached remainder bills at `input`, reads at
`cache_read`, writes at `cache_write`. A price row without those keys bills
cached tokens at `input`, which overstates and never understates. Only the
Anthropic rows carry them, from the Claude API pricing reference: reads at
0.1x input for Sonnet 5.5 and Haiku 5.5 and 0.05x for Opus 5.5, 5-minute writes
at 1.25x. The OpenAI rows have none. `ai_audit_logs.cache_read_tokens` and
`cache_write_tokens` record the buckets (migration
`2026_11_17_204024_add_cache_tokens_and_step_number_to_ai_audit_logs_table.php`).

Long-context tiers. A row may carry `long_context => [above, input, output]`,
optionally with its own `cache_read` and `cache_write`. When `inputTokens`, the
whole prompt, exceeds `above`, the whole request bills at the tier rates, as the
provider bills it; cache tokens there bill at the tier's own cache prices or, lacking
them, at the tier's `input`. Only `claude-haiku-5-5` has a tier (over 100k tokens).
No other row has one, because no source in the repo confirms it. A malformed
optional key is dropped and the base price used, never thrown on.

Idempotency. A run writes one row per generation step, and `step_number` is set
only on a metered success row. `(run_id, step_number)` is unique, so a replayed
step collides on insert, `RecordAudit::record()` catches the violation, and the
rolled-back transaction leaves one log and one debit. Error rows leave
`step_number` null and are not constrained. A retry that reaches the provider
again carries a new invocation id and is billed, since the provider billed it.
`tests/Feature/Ai/AiPricingTest.php` walks every tier and failover model and
fails when one has no price, and `php artisan app:doctor` also reports an
unpriced configured model. The OpenAI prices in `config/ai.php` are unconfirmed,
and its pricing comment says where each figure came from.

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

## The credit ledger

Credit is a ledger, not a column: `app/Models/AiCreditLedgerEntry.php` appends
signed micro-amounts and the balance is their sum. Two actions are the only way
in, per the model's own docblock.

`app/Actions/DebitAiCredits.php` takes a positive amount and posts it negative.
`RecordAudit` calls it once per invocation with the audit row as the reference
and no actor. A caller that does pass an actor has `create` on
`AiCreditLedgerEntry` authorized inside the organization's context.
`app/Actions/GrantAiCredits.php` is the mirror image: a positive amount posts
positive, with the same optional actor check. The seed and console paths pass no
actor. Both throw `InvalidArgumentException` for an amount of zero or less.
`tests/Feature/Ai/AiCreditTest.php` exercises them. No caller of `GrantAiCredits` was found in `app/`, so
how credit gets granted outside tests is not documented.

## Statuses and the quota error

`app/Enums/AiAuditStatus.php` has three values: `ok`, `blocked` and `error`.
`AiQuota` excludes `blocked` from its counts, and `SummarizeAiUsage` counts them
into the `blocked` total.

`app/Exceptions/AiQuotaExceededException.php` is thrown by `EnforceQuota` and
renders a JSON 429 with `error: ai_quota_exceeded`, so the client can tell a
quota refusal from a provider failure. `app/Providers/AiServiceProvider.php`
also registers a friendly message for it as a 429.

## The usage page and its Data

`app/Http/Controllers/OrganizationAiUsageController.php` serves
`settings/organization/ai-usage`. It takes the bound organization, authorizes
`view` on it, and renders `organization/ai-usage` with `SummarizeAiUsage` over the
last 30 days. `app/Data/AiUsageData.php` is the report shape (`since`, `runs`,
`tokens`, `cost_micros`, `blocked`, plus `agents` and `tiers`), and
`app/Data/AiUsageRowData.php` is one row of either breakdown (`name`, `runs`,
`tokens`, `cost_micros`). The same `AiUsageData` answers `ai:usage --json`, per
its docblock.
