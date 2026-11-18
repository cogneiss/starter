---
title: AI memory
status: current
supersedes: []
code_refs:
    - app/Ai/Memory/AssistantMemory.php
    - app/Models/AiMemory.php
    - app/Ai/Tools/RememberFact.php
    - tests/Feature/Ai/AssistantMemoryTest.php
    - app/Ai/Actions/RememberAssistantFact.php
    - app/Ai/Agents/Assistant.php
updated: 2026-11-18
---

# AI memory

The assistant remembers facts about one person in one organization. Both halves
of that sentence are the control.

## The predicate

`AssistantMemory::query()` filters on `organization_id` **and** `user_id`, in one
private method. Filtered by organization alone it hands a colleague someone
else's notes; filtered by user alone it follows a person from one organization
into another, which is the leak the rest of the layer spends its time
preventing.

Keeping the predicate in one place means there is one thing to remove and one
test to go red when it is — `tests/Feature/Ai/AssistantMemoryTest.php` seeds a
fact for the same person in a second organization and asserts it is not read
here. Drop either `where` and the tests redden.

Unlike the rest of the AI models this is an explicit predicate rather than the
global scope, because the second half of it — the user — is not something the
scope knows about.

## Reading and writing

`recall()` returns this person's facts, one per line. `app/Ai/Agents/Assistant.php`
fences them as untrusted content before they reach the system prompt, because
remembered text is a customer's words ([[domains/ai-injection-defense]]). The
fence sits in the agent's `instructions()` so `tests/Unit/Conventions/InjectionConventionTest.php`
can see it. With no facts the agent adds nothing rather than an empty fence: a
fence promising facts that are not there is just a worse prompt.

Because the instructions end in this person's facts, the assistant's
`#[CacheInstructions]` breakpoint is per member, and it only applies on Anthropic
and Bedrock. The tool list is identical for everyone, so its
`#[CacheToolDefinitions]` breakpoint is shared.

`remember()` writes one fact under a key, replacing whatever was there, inside a
transaction that also evicts. An agent does not call it. `app/Ai/Tools/RememberFact.php`
only proposes the fact, and the person confirms it at `ai/confirm/{token}`, which
runs `app/Ai/Actions/RememberAssistantFact.php` and stores the row with source
`confirmed`. A fact planted by injected text is memory poisoning, so nothing a
model says is stored until a human agrees. Key and value limits (100 and 500
characters) are enforced when the proposal is made.

## The cap

`ai.memory.max_facts` (default 20) bounds both the read and the eviction. Every
fact is read into the system prompt on every request, so an uncapped memory is a
bill that grows on its own. Past the cap the least recently touched fact is
dropped. Expired facts — `expires_at` in the past — are not read at all.
