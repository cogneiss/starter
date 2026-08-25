---
title: AI metering and quotas
status: current
supersedes: []
code_refs:
    - app/Ai/Middleware/RecordAudit.php
    - app/Ai/Middleware/EnforceQuota.php
    - app/Support/AiQuota.php
    - app/Support/AiPricing.php
    - app/Models/AiAuditLog.php
    - app/Models/AiCreditLedgerEntry.php
    - app/Actions/SummarizeAiUsage.php
    - app/Console/Commands/AiUsageCommand.php
    - tests/Feature/Ai/AiQuotaTest.php
    - tests/Feature/Console/AiUsageCommandTest.php
updated: 2026-08-25
---

# AI metering and quotas

Every agent run is recorded, priced and counted. The same rows answer three
questions: what happened, what it cost, and whether the next request is allowed.

## The record

`app/Ai/Middleware/RecordAudit.php` writes an `AiAuditLog` row for each run —
organization, user, agent, provider, model, tier, token counts, cost in micros,
duration and status — and debits the credit ledger in the same transaction. It
runs last in the pipeline, so the record describes what actually went out.
Refusals are recorded too, with status `Blocked` and the reason.

Cost comes from `app/Support/AiPricing.php`, which reads `ai.pricing` keyed by
provider and model. A model with no entry is priced at zero, which is why
`php artisan app:doctor` reports an unpriced configured model: runs that cost
nothing never trip the budget.

## The limits

`app/Support/AiQuota.php` measures three, all from the audit log:

| Limit                             | Config key                              | Default       |
| --------------------------------- | --------------------------------------- | ------------- |
| requests per member per hour      | `ai.quotas.user_requests_per_hour`      | 60            |
| requests per organization per day | `ai.quotas.org_requests_per_day`        | 2000          |
| spend per organization per month  | `ai.quotas.org_budget_micros_per_month` | $50 in micros |

Counting reads the log rather than a counter, so a limit cannot be reset by
anything short of time passing. Blocked requests are not counted: refusing one
costs nothing, so it must not push the member further over.

`app/Ai/Middleware/EnforceQuota.php` runs the check first in the pipeline, inside
the agent's organization, and refuses before a prompt reaches a provider.

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
