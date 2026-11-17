---
title: AI assistant
status: current
supersedes: []
code_refs:
    - app/Ai/Agents/Assistant.php
    - resources/prompts/assistant.md
    - app/Actions/SendAssistantMessage.php
    - app/Actions/AssistantConversations.php
    - app/Actions/AssistantBlocks.php
    - app/Http/Controllers/AssistantController.php
    - app/Http/Requests/SendAssistantMessageRequest.php
    - database/migrations/2026_11_15_100000_make_agent_conversation_participant_id_a_string.php
    - routes/web.php
    - resources/js/pages/assistant/index.tsx
    - resources/js/pages/assistant/show.tsx
    - resources/js/components/assistant-chat.tsx
    - tests/Feature/Ai/AssistantTest.php
    - tests/Browser/AssistantTest.php
updated: 2026-11-17
---

# AI assistant

The assistant is a chat inside the application. A member asks about their
organization, the assistant reads records through tools, and anything that
would change data comes back as a proposal the member confirms. See
[[domains/ai-agents-and-tools]] for the tool and confirm machinery it reuses.

## The agent

`app/Ai/Agents/Assistant.php` is organization scoped and runs the default
middleware, so quota, fencing, topic filter and audit apply like any other
agent. It uses the `standard` tier, and
it carries `#[CacheInstructions]` and `#[CacheToolDefinitions]`, which stamp one
cache breakpoint each and apply to Anthropic and Bedrock only (the class
docblock explains the split between the shared tool list and the per-member
instructions). Its tools are the knowledge search, the record
list and show tools, `RememberFact` and `ProposeAction`. None writes. The system
prompt is `resources/prompts/assistant.md`, followed by the member's fenced
memory when there is any.

## Saved conversations

Each turn goes through `SendAssistantMessage`, which creates the conversation
row first and then continues it with the SDK. Creating it first does two things:
it sets `organization_id` and `user_id`, which the SDK does not know about, and
it titles the conversation from the first message so naming it costs no provider
call.

`AssistantConversations` is the one query for conversations. It filters by
organization and by member, so another member's id and another organization's id
both come back as a 404 from `findOrFail`, never a 403.

The SDK stores the participant key in a bigint column, and users here are keyed
by uuid. A migration changes `participant_id` to a string on both tables, since
the first saved turn would otherwise fail on insert.

## Routes

`assistant`, `assistant/new` and `assistant/{conversation}` are in the
authenticated organization area. `POST ai/assistant` is inside the
`throttle:30,1` group next to the other AI write routes (`routes/web.php`). The
whole area answers 404 unless `ai-assist-enabled` is on for the organization,
which is off by default.

## Plain request and response, not streaming

A turn returns one JSON body with the reply text and any confirm blocks. The
turn has to be saved, and confirm blocks have to be lifted from the tool results
and checked against the organization (`AssistantBlocks`) before anything
renders, so there is nothing safe to show halfway through. The existing
newline-delimited stream belongs to the block composer, a different agent. A
quota refusal is a 429 with a friendly message, which the chat shows as its own
state.

## Screens

The list page defers its data and shows a skeleton while it loads, then either
the conversations or an empty state. The chat page shows an intro when empty, a
sending indicator, and distinct failure and quota messages. A confirm block is
rendered by the existing confirm component, which posts to the confirm route.
The sidebar entry appears only when the `aiAssist` shared prop is true. Strings
are in `lang/en/ui.php` and `lang/nl/ui.php` under `assistant`.

## Tests

`tests/Feature/Ai/AssistantTest.php` covers sending with a faked agent, saving
and listing, 404 for another member and another organization, tool setup,
authorization, quota and the guest redirect. `tests/Browser/AssistantTest.php`
checks both screens for accessibility.
