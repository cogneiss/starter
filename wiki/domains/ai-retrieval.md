---
title: AI retrieval
status: current
supersedes: []
code_refs:
    - app/Models/AiDocument.php
    - app/Ai/Tools/SearchKnowledge.php
    - app/Support/AiRetrieval.php
    - app/Console/Commands/AiInstallCommand.php
    - tests/Feature/Ai/RetrievalTest.php
    - app/Jobs/IndexAiDocument.php
    - app/Jobs/RemoveAiDocuments.php
    - app/Resources/SyncKnowledgeIndex.php
    - app/Resources/IndexesKnowledge.php
    - app/Console/Commands/AiReindexCommand.php
    - tests/Feature/Ai/KnowledgeIndexTest.php
    - tests/Feature/Ai/AiReindexCommandTest.php
updated: 2026-11-18
---

# AI retrieval

Agents can search the organization's own content. Chunks live in `ai_documents`
with their embedding, and `SearchKnowledge` runs nearest-neighbour over them with
pgvector.

## The global scope is the whole security story

A similarity search has no natural boundary. Nearest-neighbour over an unscoped
table is a query that cheerfully returns another organization's paragraphs
because they happened to be closer. `app/Models/AiDocument.php` uses
`BelongsToOrganization`, so every query is filtered by the bound organization and
the only way out is an explicit `withoutOrganizationScope()` call someone can
read in a diff.

`tests/Feature/Ai/RetrievalTest.php` seeds a document in another organization and
asserts it never comes back. Drop the trait and that test reddens — the leak is
caught by a test rather than by a customer.

Retrieved passages reach the prompt fenced, one fence per document, because a
document's body is customer text like any other ([[domains/ai-injection-defense]]).

## When retrieval is not available

`app/Support/AiRetrieval.php` needs two things a checkout may not have: a `pgsql`
connection carrying pgvector, and a configured embedding provider. Missing either
is a supported state, not an error — `SearchKnowledge::registeredFor()` returns
nothing, agents answer without retrieval, and `php artisan app:doctor` says which
half is missing.

An agent holding a tool that throws answers with a stack trace, so the tool is
withheld rather than allowed to fail at call time.

## Setting it up

`php artisan ai:install` creates the vector extension. On any connection other
than PostgreSQL it is a deliberate no-op: everything except vector search works
on SQLite, which is what `composer test:sqlite` proves. The database itself is
[[operations/setup]].

## Indexing

`app/Jobs/IndexAiDocument.php` is a queued job that embeds one piece of
organization content, so the embedding network call never happens in a request.
It takes an organization id, a source type and id, a title and the content. It
runs under `WithOrganizationContext`. The content is split into 1000-character
chunks (`CHUNK`), because one embedding of a whole document averages everything
into a single point that matches nothing well. Each run first deletes the rows
for the same source type and id, so re-indexing replaces rather than stacks, then
calls `Embeddings::for()` at 1536 dimensions and writes one `AiDocument` row per
chunk. A resource adapter feeds it by implementing `app/Resources/IndexesKnowledge.php`
(`knowledgeTitle()` and `knowledgeContent()`). It is a separate interface so
`ResourceContract` keeps its six methods, and none of the six shipped adapters
opts in: members, users, audit rows and tokens are personal data, and whatever an
adapter returns is embedded and sent to the provider. The source type is the
resource key.

`app/Resources/SyncKnowledgeIndex.php` subscribes to `eloquent.saved` and
`eloquent.deleted` of the models whose resource opted in, and of no other model. A save of an opted-in record dispatches `IndexAiDocument`;
a delete dispatches `app/Jobs/RemoveAiDocuments.php`, which deletes that source's
rows. Both dispatch after the transaction commits and run under
`WithOrganizationContext`. A resource that has not opted in is never listened to.

`php artisan ai:reindex {resource}` backfills: it refuses a resource that has not
opted in, and for one that has, walks each organization under
`OrganizationContext::runAs()` and dispatches one job per record.
`tests/Feature/Ai/KnowledgeIndexTest.php` and
`tests/Feature/Ai/AiReindexCommandTest.php` cover it against a fixture resource
with the embeddings fake and `Queue::fake()`.
