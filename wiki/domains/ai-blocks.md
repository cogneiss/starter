---
title: AI blocks
status: current
supersedes: []
code_refs:
    - app/Ai/Blocks/AiBlock.php
    - app/Ai/Blocks/BlockCollection.php
    - app/Enums/AiBlockType.php
    - app/Actions/StreamBlocks.php
    - resources/js/components/ai/blocks/AiBlocks.tsx
    - resources/js/pages/organization/ai-usage.tsx
    - tests/Feature/Ai/AiBlockTest.php
updated: 2026-08-31
---

# AI blocks

An agent returns blocks, never HTML. Rendering markup a model wrote is an XSS
hole, and "the model returned prose we then parse" is the same hole with more
steps. A block is a typed shape the frontend knows how to draw.

## The union

`app/Enums/AiBlockType.php` names seven: `text`, `markdown`, `table`, `list`,
`metric`, `form`, `confirm`. Each has a PHP class under `app/Ai/Blocks`
implementing `AiBlock`, and a React component under
`resources/js/components/ai/blocks`. `AiBlocks.tsx` switches on `type`
exhaustively, so a block added on one side without the other fails the type
check rather than rendering nothing.

## The model names a block; the application decides whether it has one

`BlockCollection::fromPayloads()` takes what the model produced and returns only
the payloads that map to a known type and validate against that block's shape.
A payload naming a type that does not exist is dropped, not rendered. That is
the trust boundary: the model chooses from a menu the application wrote.

## Streaming

`app/Actions/StreamBlocks.php` decodes a streamed answer line by line and yields
blocks as they complete, so a long answer draws progressively instead of after.
A line that does not decode into a block is skipped rather than shown.

An answer that produced no blocks at all — every payload dropped, or a model
that said nothing usable — renders the kit's `EmptyState` rather than an empty
div, so "nothing came back" is a sentence on the screen instead of a layout that
looks broken ([[domains/ux-primitives]]).

## Blocks are not only for agent answers

`resources/js/pages/organization/ai-usage.tsx` builds `MetricBlock` and
`TableBlock` payloads by hand from server data. A figure on that page and the
same figure inside an agent's briefing are laid out identically, because they are
the same component. The reporting behind that page is
[[domains/ai-metering-and-quotas]].

The confirm block is the visible half of [[domains/ai-confirm-tokens]]: it
carries the token and the summary of what will happen, and the button is what
consumes it.
