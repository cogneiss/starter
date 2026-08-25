---
paths:
    - "app/Ai/**"
    - "app/Mcp/**"
    - "config/ai.php"
    - "tests/Evals/**"
---

# The AI layer

## Tools are read-only; writes go through a proposal and a confirm token

A tool answers questions. It never writes, never opens a transaction, and never
calls `App\Actions\ConsumeConfirmToken` — `tests/Unit/ArchTest.php` fails the
build when one does. `App\Ai\Tools\ProposeAction` is the single named exemption,
and the only thing it writes is the proposal itself.

To make a model able to change something, register the action in
`app/Ai/ConfirmableActions.php` and let the agent propose it. The proposal mints
a single-use token; a human with the permission spends it at
`POST ai/confirm/{token}`; `ConsumeConfirmToken` marks `consumed_at` inside the
same transaction as the write, so a replayed token is refused rather than run
twice.

## Every tool calls `authorizeFor()` before it returns anything

A tool is a new caller of the existing policies, not an exemption from them.
Resolve the acting user and call `authorizeFor()` on the ability the equivalent
controller would check (`wiki/architecture/two-gate-authorization.md`). The agent's own
organization scope is not authorization: it decides which rows exist, not who may
read them.

## Fence untrusted content

Anything a person or a stored record supplied goes through
`App\Support\UntrustedContent::fence()` before it reaches a model. Instructions
inside fenced text are data. Never concatenate a record's field into an
instruction string.

The egress allowlist (`AI_EGRESS_ALLOWLIST`) and the denied-topic list are the
matching outbound control. Both are empty by default, which denies rather than
permits.

## Model names are literals, tiers are config

Never write a model-name literal in an agent. `config/ai.php` maps the two tiers
— `cheap` and `smart` — onto provider and model, and an agent picks a tier. A
literal in a class is a model rename away from a production failure that no test
sees.

## No real provider call in the blocking suite

`tests/Pest.php` fakes every agent under `app/Ai/Agents` with
`preventStrayPrompts()` before each test in `Feature/Ai`. Do not undo it locally
to "check the real answer". `tests/Evals/` is the one place a provider is
reached, it is excluded from `composer test`, and it skips itself with no key
configured.

## Agents are scoped and carry the default middleware

Every class in `app/Ai/Agents` implements `App\Ai\Contracts\OrganizationScoped`
and `Laravel\Ai\Contracts\HasMiddleware`, and never touches `DB` or the write
actions. That is an architecture test, so a new agent that forgets fails the
build rather than leaking across organizations.

## `laravel/mcp` is vendored, not required

It arrives through `laravel/boost` at 0.9.4. Do not add it to `composer.json`
and do not bump it. The server in `app/Mcp` ships disabled.
