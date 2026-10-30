---
title: AI agents and tools
status: current
supersedes: []
code_refs:
    - app/Ai/Agents/InvitationDrafter.php
    - app/Ai/Agents/DashboardBriefing.php
    - app/Ai/Agents/FieldDrafter.php
    - app/Ai/DraftableFields.php
    - app/Actions/DraftFieldText.php
    - app/Http/Controllers/AiDraftController.php
    - tests/Feature/Ai/AiAssistTest.php
    - app/Ai/Tools/ListResourceRecords.php
    - app/Ai/Tools/ShowResourceRecord.php
    - app/Ai/Tools/ProposeAction.php
    - app/Ai/Tools/Concerns/AuthorizesToolCall.php
    - tests/Feature/Ai/ToolAuthorizationTest.php
updated: 2026-10-31
---

# AI agents and tools

An agent is a class implementing `Laravel\Ai\Contracts\Agent` with the
`Promptable` trait, living in `app/Ai/Agents`. Two ship as product features —
`InvitationDrafter` and `DashboardBriefing`, documented from the user's side in
[[features/organizations]], plus `FieldDrafter`, below.

## Draft with AI

`FieldDrafter` rewrites or drafts the text of one form field. It runs the
default middleware (quota, fence, topic filter, audit and credit debit), so a
draft is metered like any other run, and it uses the `cheap` tier.
`POST /ai/draft` (`AiDraftController`) validates a `field` key from
`DraftableFields`, then `DraftFieldText` refuses with a 404 unless the
`ai-assist-enabled` flag is on for the bound organization and the user passes
the field's ability. The organization comes from the bound context, never from
the request body, so a foreign organization cannot be targeted. Adding a
draftable field is one entry in `DraftableFields` plus an
`<AiDraftButton field=...>` in the form; the first is the announcement body on
the admin announcement form. `tests/Feature/Ai/AiAssistTest.php` covers it.

Agents that take tools implement `HasTools` and return them from `tools()`. The
tools are built for one member in one organization and hold both: nothing in a
tool reads an ambient "current user".

## What the tools do

| Tool                                   | Job                                        |
| -------------------------------------- | ------------------------------------------ |
| `app/Ai/Tools/ListResourceRecords.php` | list records of a registered resource      |
| `app/Ai/Tools/ShowResourceRecord.php`  | read one record                            |
| `app/Ai/Tools/SearchKnowledge.php`     | vector search over organization documents  |
| `app/Ai/Tools/RememberFact.php`        | write one fact to assistant memory         |
| `app/Ai/Tools/ProposeAction.php`       | propose a write, returning a confirm token |

The read tools go through the resource registry rather than touching models, so
a resource that is not registered is not reachable by an agent at all. The
registry itself is [[domains/resources]].

## A tool never outranks its member

Every tool calls `authorizeFor()` from
`app/Ai/Tools/Concerns/AuthorizesToolCall.php` before it does anything. That
asks the same policy the controller would have asked, through
`Gate::forUser($user)`, and on refusal writes an `AiAuditLog` row with status
`Blocked` before rethrowing.

The reason it is not optional: the model chooses which tool to call, and the
model reads content the application does not control. Authorization at the tool
boundary is the point where a persuaded model stops being dangerous.

`tests/Feature/Ai/ToolAuthorizationTest.php` proves it, and an architecture test
fails when a class under `app/Ai/Tools` is added without the call — remove the
`authorizeFor()` line from a tool and the suite reddens rather than passing with
a hole in it.

## Writes are proposed, never performed

No tool writes application state. `ProposeAction` validates the request against
a `ConfirmableAction`, mints a confirm token, and returns it for the person to
approve in the application. See [[domains/ai-confirm-tokens]].

## Adding an agent

1. `php artisan make:agent` and move the class into `app/Ai/Agents`.
2. Use `HasDefaultMiddleware` and `OrganizationScopedAgent` — see
   [[domains/ai-layer-overview]].
3. Ask for a tier, never a model-name literal.
4. Give it tools that already authorize, or write one that does.
5. Test it with `Agent::fake()`; the blocking suite never reaches a provider
   ([[domains/ai-evals]]).
