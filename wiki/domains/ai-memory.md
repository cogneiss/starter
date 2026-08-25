---
title: AI memory
status: current
supersedes: []
code_refs:
    - app/Ai/Memory/AssistantMemory.php
    - app/Models/AiMemory.php
    - app/Ai/Tools/RememberFact.php
    - tests/Feature/Ai/AssistantMemoryTest.php
updated: 2026-08-25
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
here. Drop either `where` and the suite reddens.

Unlike the rest of the AI models this is an explicit predicate rather than the
global scope, because the second half of it — the user — is not something the
scope knows about.

## Reading and writing

`instructions()` returns the memory block for the system prompt, fenced as
untrusted content because remembered text is a customer's words
([[domains/ai-injection-defense]]). With no facts it returns an empty string
rather than an empty fence: a fence promising facts that are not there is just a
worse prompt.

`remember()` writes one fact under a key, replacing whatever was there, inside a
transaction that also evicts. `app/Ai/Tools/RememberFact.php` is how an agent
reaches it, authorized like every other tool
([[domains/ai-agents-and-tools]]).

## The cap

`ai.memory.max_facts` (default 20) bounds both the read and the eviction. Every
fact is read into the system prompt on every request, so an uncapped memory is a
bill that grows on its own. Past the cap the least recently touched fact is
dropped. Expired facts — `expires_at` in the past — are not read at all.
