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
    - app/Http/Controllers/AiConfirmController.php
    - app/Data/AiConfirmTokenData.php
    - app/Resources/Definitions/AiConfirmTokenResource.php
    - app/Exceptions/InvalidConfirmToken.php
    - app/Ai/Actions/InviteMember.php
    - app/Ai/Actions/RememberFactData.php
updated: 2026-11-18
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
tool that mints them is in [[domains/ai-agents-and-tools]]. `remember-fact` is a built-in action merged in by `app/Ai/ConfirmableActions.php`.

## The confirm route

`app/Http/Controllers/AiConfirmController.php` handles `POST ai/confirm/{token}`.
It calls `ConsumeConfirmToken` for the signed-in user, flashes a success toast
(`Done.`) and goes back. It does no checking of its own. The route sits inside a
`throttle:30,1` group in `routes/web.php`.

## What a refusal looks like

`app/Exceptions/InvalidConfirmToken.php` has one named constructor per refusal in
the table above, plus `unmappedAction()`. The messages are plain and never repeat
the payload. The exception renders itself: it flashes an error toast and redirects
back, because a replay or an expired token is an ordinary answer to someone who
clicked a stale button, not a server fault.

## What the client sees

`app/Data/AiConfirmTokenData.php` is the shape sent to the browser: id, action,
summary and `expires_at`. The payload is left out on purpose, so the browser
cannot edit what will run.

`app/Resources/Definitions/AiConfirmTokenResource.php` registers confirmations as
the `ai-confirm-tokens` resource, so they can be listed and searched by
`summary` and `action`. Its `scopedQuery()` adds `user_id = auth()->id()` on top
of the organization scope, matching what `AiConfirmTokenPolicy::view` allows, and
returns no rows when nobody is signed in. A record links back to the dashboard,
where the assistant panel raised it.

## Built-in and shipped actions

`app/Ai/Actions/InviteMember.php` is the shipped example, registered as
`invite-member` in `config/ai.php`. It adds no logic: `confirm()` calls
`CreateOrganizationInvitation` for the bound organization, so an invitation an
agent proposed and one a person typed run the same code under the same policy.
Its ability is `members.invite`.

`app/Ai/Actions/RememberFactData.php` is the payload of the `remember-fact`
proposal: a `key` of at most 100 characters and a `value` of at most 500. The
caps keep one confirmed fact short enough to sit in a prompt. What happens on
confirmation is in [[domains/ai-memory]].
