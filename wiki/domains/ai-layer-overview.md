---
title: AI layer overview
status: current
supersedes: []
code_refs:
    - config/ai.php
    - app/Support/AiAvailability.php
    - app/Support/AiTier.php
    - app/Ai/Agents/Concerns/HasDefaultMiddleware.php
    - app/Ai/Concerns/OrganizationScopedAgent.php
    - app/Console/Commands/AiInstallCommand.php
updated: 2026-08-25
---

# AI layer overview

The starter ships a product AI layer built on `laravel/ai`: agents that answer
inside one organization, tools that never outrank the member who triggered them,
writes that are proposed rather than performed, and a record of every run.

It boots with no provider key. `App\Support\AiAvailability` reads
`config/ai.php`; when no provider carries a key — or `AI_FAKE=true` is set —
every agent is answered by the SDK's fake gateway, so a fresh checkout demos and
its tests pass without an account anywhere.

## The pieces

| Directory             | What lives there                                                      |
| --------------------- | --------------------------------------------------------------------- |
| `app/Ai/Agents/`      | the agents: `InvitationDrafter`, `DashboardBriefing`, `BlockComposer` |
| `app/Ai/Tools/`       | what an agent may do, each one authorized against a policy            |
| `app/Ai/Middleware/`  | quota, fencing, topic filtering and audit, in that order              |
| `app/Ai/Blocks/`      | the typed answer shapes the frontend can render                       |
| `app/Ai/Memory/`      | what the assistant remembers about one person in one organization     |
| `app/Support/Ai*.php` | availability, tiers, pricing, quota, retrieval and egress             |
| `app/Mcp/`            | the same registry surface, offered to an MCP client                   |

## The kernel every agent runs

Agents use `App\Ai\Agents\Concerns\HasDefaultMiddleware`, which returns one
pipeline in one order — `EnforceQuota`, `FenceUntrustedInput`, `FilterTopics`,
`RecordAudit`. Quota is first so a prompt that will be refused is never paid
for; audit is last so the record describes what actually went out. The order is
the control, and it lives in one file so there is one place to change it and one
test to go red when it changes.

`App\Ai\Concerns\OrganizationScopedAgent` carries the organization and the user
an agent runs for. Nothing in the layer reads "the current organization" out of
the air: the middleware binds it through `OrganizationContext`, and the global
scope of [[domains/multi-tenancy]] does the filtering.

## Choosing a model

Agents ask for a tier, not a model name. `App\Support\AiTier::for()` resolves
`cheap` or `smart` from `config/ai.php` into a provider and a model, with
failover when a second provider is configured. A model-name literal in an agent
class is the thing this exists to prevent — see [[domains/ai-metering-and-quotas]]
for how a tier's price is read back out of the same config.

## Getting it running

`php artisan ai:install` prepares the database side (see
[[domains/ai-retrieval]] for the pgvector half). `php artisan app:doctor`
reports which providers carry keys, whether the fake gateway is answering,
whether retrieval is available, and whether every configured model has a price.

## Where the rest is written down

- [[domains/ai-agents-and-tools]] — what an agent may do and how it is checked
- [[domains/ai-injection-defense]] — the fence around untrusted content
- [[domains/ai-confirm-tokens]] — proposal, approval, single use
- [[domains/ai-blocks]] — the answer shapes the frontend renders
- [[domains/ai-metering-and-quotas]] — audit, cost, limits, reporting
- [[domains/ai-retrieval]] — embeddings and pgvector search
- [[domains/ai-memory]] — per-person, per-organization recall
- [[domains/ai-mcp-server]] — the registry over MCP
- [[domains/ai-evals]] — grading prompts against real providers
