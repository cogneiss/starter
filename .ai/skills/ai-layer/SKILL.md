---
name: ai-layer
description: "Use when adding or changing anything under app/Ai, app/Mcp, config/ai.php or tests/Evals in this starter kit: a new agent, a new tool, a new confirmable action, a UI block an agent answers with, retrieval, assistant memory, quotas and metering, or an eval. Covers the read-only tool rule, authorizeFor(), the propose-then-confirm write path and its single-use token, fencing untrusted content, tiers instead of model-name literals, and the ban on real provider calls in the blocking suite. The mistakes here are cross-organization leaks and writes nobody confirmed, so read it before editing."
license: MIT
metadata:
    author: cogneiss
---

# The AI layer

Four kinds of thing live here, and they have different rules.

| Thing  | Lives in         | May it write?                   |
| ------ | ---------------- | ------------------------------- |
| agent  | `app/Ai/Agents`  | no                              |
| tool   | `app/Ai/Tools`   | no — `ProposeAction` is exempt  |
| block  | `app/Ai/Blocks`  | no, it is a render payload      |
| action | `app/Ai/Actions` | yes, only via a confirmed token |

## Adding an agent

An agent is a vertical: one job, one organization. It implements
`App\Ai\Contracts\OrganizationScoped` and `Laravel\Ai\Contracts\HasMiddleware`,
and it never touches `DB`, `ConsumeConfirmToken` or `CreateOrganizationInvitation`.
`tests/Unit/ArchTest.php` enforces all of that, so a forgotten interface is a red
build rather than a leak.

Pick a tier, never a model name. `config/ai.php` maps `cheap` and `smart` onto a
provider and a model; a literal in a class survives a model rename until
production finds it.

## Adding a tool

A tool reads. Before returning anything it resolves the acting user and calls
`authorizeFor()` on the ability the equivalent controller would check. The
organization scope decides which rows exist; the policy decides who may see them,
and a tool is a new caller of your policies rather than an exemption from them.

## Making a model able to change something

Do not give the tool a write. Register the action in
`app/Ai/ConfirmableActions.php`, let the agent return a proposal through
`App\Ai\Tools\ProposeAction`, and let a human spend the minted token at
`POST ai/confirm/{token}`. `App\Actions\ConsumeConfirmToken` stamps `consumed_at`
inside the same transaction as the write, so a replayed token is refused. The
invitation vertical is the worked example.

## Untrusted text

Anything a person or a stored record supplied goes through
`App\Support\UntrustedContent::fence()` first. Instructions found inside fenced
text are data, not orders. The egress allowlist and denied-topic list are the
outbound half, and both default to empty, which denies.

## Testing

`tests/Pest.php` fakes every agent with `preventStrayPrompts()` before each
`Feature/Ai` test. Script your own fake; do not remove the guard to see a real
answer. `tests/Evals/` is the only place that reaches a provider, it is out of
`composer test`, and it skips itself with no key set.

## Why it is shaped this way

- `wiki/domains/ai-layer-overview.md` — the map
- `wiki/domains/ai-agents-and-tools.md` — agents, tools, the read-only rule
- `wiki/domains/ai-confirm-tokens.md` — propose, confirm, single use
- `wiki/domains/ai-injection-defense.md` — fencing and egress
- `wiki/domains/ai-metering-and-quotas.md` — budgets, quotas, the audit log
- `wiki/domains/ai-blocks.md`, `wiki/domains/ai-retrieval.md`,
  `wiki/domains/ai-memory.md`, `wiki/domains/ai-mcp-server.md`,
  `wiki/domains/ai-evals.md`
