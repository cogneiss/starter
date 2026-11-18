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
    - resources/js/lib/ai-blocks.ts
    - app/Ai/Blocks/AiFormField.php
    - app/Ai/Blocks/ConfirmBlock.php
    - app/Ai/Blocks/FormBlock.php
    - app/Ai/Blocks/ListBlock.php
    - app/Ai/Blocks/MarkdownBlock.php
    - app/Ai/Blocks/MetricBlock.php
    - app/Ai/Blocks/TableBlock.php
    - app/Ai/Blocks/TextBlock.php
    - app/Enums/AiMetricTrend.php
    - app/Ai/Agents/BlockComposer.php
    - app/Http/Controllers/AiBlockController.php
    - app/Http/Controllers/AiProposalController.php
updated: 2026-11-18
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
A line that does not decode into a block is skipped rather than shown, and each
skip logs a warning with a reason (`invalid_json` or `not_a_block`) and the line
length, never its text. The browser client in `resources/js/lib/ai-blocks.ts`
warns on a dropped line and keeps reading instead of ending the stream.

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

## The block classes

Each class under `app/Ai/Blocks` is a Data object with a fixed `type`.

| Class | Carries | Note |
| ----- | ------- | ---- |
| `TextBlock.php` | `text` | plain text |
| `ListBlock.php` | `items`, `ordered` | list of strings |
| `MetricBlock.php` | `label`, `value`, optional `delta` and `trend` | `trend` is `app/Enums/AiMetricTrend.php`: `up`, `down` or `flat` |
| `TableBlock.php` | `columns`, `rows` | throws when a row has a different cell count than there are columns |
| `MarkdownBlock.php` | `markdown`, computed `html` | the only block that renders markup |
| `FormBlock.php` | `action`, computed `summary` and `fields` | see below |
| `ConfirmBlock.php` | `token`, computed `summary` and `expires_at` | see below |

`MarkdownBlock` derives `html` in its constructor with ``Str::markdown()`` set to
strip raw HTML and refuse unsafe links. `html` is a computed property, so a
payload that supplies its own `html` does not overwrite the sanitizer's output
and produces no block.

`FormBlock` takes an `ai.actions` key and prefilled values. Its fields come from
the public properties of the action's own Data class
(`ConfirmableActions::find()` then reflection), so a model cannot add a field the
action does not have. A key not on the allowlist throws instead of producing a
block. The fields are `AiFormField` objects (`app/Ai/Blocks/AiFormField.php`, a
`name` and a `value`). Submitting the form posts to
`app/Http/Controllers/AiProposalController.php` (`ai/proposals`), which
validates the action key against `ConfirmableActions::all()`, keeps only
string-keyed fields, mints a token through `CreateConfirmToken` and answers with
a `ConfirmBlock` as JSON. Nothing runs at that point.

`ConfirmBlock` takes only the token id. Its constructor reads the token back with
`AiConfirmToken::query()->findOrFail()`, which goes through the organization
global scope, so another organization's token id finds nothing. The summary and
expiry are read off the stored token.

## Producing blocks

`app/Ai/Agents/BlockComposer.php` is the agent that answers in blocks. Its
instructions come from `resources/prompts/block-composer.md` and ask for one JSON
object per line. Its own docblock says this is a request, not a guarantee:
`BlockCollection` decides what becomes a block. The agent is organization scoped
and uses the default middleware.

`app/Http/Controllers/AiBlockController.php` (`POST ai/blocks`, behind the
`organization` middleware and a 30 per minute throttle in `routes/web.php`)
validates a `prompt` of at most 2000 characters, builds a `BlockComposer` for the
user and the bound organization, and streams what `StreamBlocks` yields as
`application/x-ndjson`, one `toArray()` per line, flushed as each block completes.
