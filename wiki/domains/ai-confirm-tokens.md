---
title: AI confirm tokens
status: current
supersedes: []
code_refs:
    - app/Actions/CreateConfirmToken.php
    - app/Actions/ConsumeConfirmToken.php
    - app/Models/AiConfirmToken.php
    - app/Ai/ConfirmableActions.php
    - app/Ai/Contracts/ConfirmableAction.php
    - tests/Feature/Ai/ConfirmTokenTest.php
    - tests/Feature/Ai/ConfirmTokenLockTest.php
updated: 2026-08-25
---

# AI confirm tokens

An agent may propose a write. It may not perform one. The proposal comes back as
a confirm token, and the write happens when the person approves it in the
application — under their own permissions, at that moment.

## The two halves

`app/Actions/CreateConfirmToken.php` records the action key, the payload, the
user, the organization and a signature, and sets `expires_at` from
`ai.confirm.ttl`. `app/Ai/ConfirmableActions.php` maps an action key to the
`ConfirmableAction` that knows how to validate and perform it — a key with no
mapping cannot be confirmed at all.

`app/Actions/ConsumeConfirmToken.php` is where every check lives, all of it
inside one `DB::transaction()` with the row taken `lockForUpdate()`:

| Check                                             | Refusal                  |
| ------------------------------------------------- | ------------------------ |
| the token exists                                  | `unknown()`              |
| `consumed_at` is still null                       | `consumed()`             |
| `expires_at` is in the future                     | `expired()`              |
| `user_id` matches the person confirming           | `wrongUser()`            |
| `organization_id` matches the bound organization  | `wrongOrganization()`    |
| the signature still matches the payload           | `tampered()`             |
| the action key maps to a `ConfirmableAction`      | `unmappedAction()`       |
| `Gate::forUser()` allows the action's ability now | `AuthorizationException` |

Only then is `consumed_at` set and the action run.

## Why the lock, and why single use

Two requests carrying the same token can arrive at once — a double-click, a
retried request, a replay. The row is locked for the length of the transaction
and `consumed_at` is written inside it, so the second transaction reads a
consumed token and is refused. The action executes exactly once.
`tests/Feature/Ai/ConfirmTokenLockTest.php` runs the concurrent case; remove the
`consumed_at` guard and it goes red rather than quietly inviting the same person
twice.

## Why the permission is re-checked

Permissions change between proposing and confirming. The token records what was
asked for, never what was allowed — the authority that counts is the one the
person holds when they press the button. That is the same two-gate reasoning as
[[architecture/two-gate-authorization]].

The signature covers the id, the action and the payload
(`AiConfirmToken::signatureFor()`), so an edited payload is a tampered token
rather than a cheaper way to ask for something else.

The tokens are rendered by a confirm block — see [[domains/ai-blocks]] — and the
tool that mints them is in [[domains/ai-agents-and-tools]].
