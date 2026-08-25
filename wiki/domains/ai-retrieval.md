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
updated: 2026-08-25
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
