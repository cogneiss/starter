---
title: AI evals
status: current
supersedes: []
code_refs:
    - tests/Pest.php
    - tests/bootstrap.php
    - tests/Fixtures/Ai/invitation-drafter.json
    - tests/Evals/InjectionDefenseEvalTest.php
    - tests/Evals/InvitationDrafterEvalTest.php
    - tests/Evals/DashboardBriefingEvalTest.php
    - .github/workflows/evals.yml
    - composer.json
updated: 2026-11-20
---

# AI evals

The SDK is `laravel/ai` `^1.4`, and `laravel/mcp` `^1.2` is required beside it (`composer.json`): the MCP server builds on it, and without the direct line only the dev-only `laravel/boost` would pull it in. The evals are written with `pestphp/pest-plugin-evals` `^5.1`, which adds `prompt()`, `toSatisfy()` and the LLM judge to `expect()`.

Two kinds of test exist here, and conflating them is how a suite ends up both
flaky and expensive.

## Tests: no provider, ever

The blocking suite never reaches a provider. `tests/Pest.php` fakes every agent
that ships with no scripted answer and calls `preventStrayPrompts()`, so a test
that forgets its own fake throws instead of dialling out. The protection is
central rather than per-test, because the per-test version is the one someone
forgets.

`tests/bootstrap.php` goes one step further and blanks `ANTHROPIC_API_KEY`,
`OPENAI_API_KEY` and `GEMINI_API_KEY` before the framework boots, so a key
exported in your shell never reaches a test. It clears `$_SERVER` as well as the
environment, because phpunit's `<env force>` misses `$_SERVER` and Laravel reads
it first.

Tests prove the mechanics: the prompt was fenced, the tool asked the policy, the
token was single use. Whether a real model respects any of it is not something a
fake gateway can tell you.

## Evals: the shipped agents, against a real provider

`tests/Evals/` is a separate suite, excluded from `composer test` and run by
`composer test:evals`, which passes `--evals`. Without that flag the plugin
skips every eval, and the bootstrap scrub above keeps the keys away, so
`pest` on its own never spends anything. With the flag the scrub is skipped:
the eval suite is the one place a key is meant to arrive.

Each eval prompts the real agent class, built for a factory organization and
owner inside `OrganizationContext::runAs()`, so it grades the instructions,
schema and middleware that ship rather than a copy of the prompt. Three ship:

| Eval                        | Grades                                                                 |
| --------------------------- | ---------------------------------------------------------------------- |
| `InjectionDefenseEvalTest`  | that a real model reads fenced organization data as data, not orders  |
| `InvitationDrafterEvalTest` | that the drafted `email` and `role` match the request                  |
| `DashboardBriefingEvalTest` | that a briefing reports the figures it was handed and invents none     |

Two of them also call `toSatisfy()`, which asks a judge model whether the answer
meets a written criterion. The judge is a `LaravelAiJudge` on the cheap tier from
`config/ai.php`, set in the Evals block of `tests/Pest.php`, so a model rename
moves the agents and the judge together. That block also skips the suite when
the cheap tier's own provider has no key. Another provider's key is not enough,
because the agents and the judge would both fail on the missing one.

Cases live in `tests/Fixtures/Ai/*.json` as a `cases` list, read by the
`evalFixture()` and `evalCases()` helpers in `tests/Pest.php`. Adding a case is a
JSON edit, not a new test. The fixtures hold inputs and expectations only; the
prompt is the agent's own, so there is no captured prompt to drift from it.

The invitation drafter answers through a structured schema of `email` and
`role`. An earlier case checked that a draft never claims the invitation was
sent, but the schema cannot express that claim, so the case was dropped rather
than kept as a check that always passes.

## Why they never run on a pull request

`.github/workflows/evals.yml` runs weekly and on request, never on a pull
request. Evals cost money, and a model can change under you without any commit,
so a branch that did nothing wrong should not go red for it. A regression fails
the scheduled run and opens an issue labelled `evals` for someone to read. The
job runs on in-memory SQLite, so it needs only the `ANTHROPIC_API_KEY` secret and
no database service. The blocking gates are in [[architecture/fast-blocking-gates]].

## What else `tests/Pest.php` carries

The file is shared ground for the full suite, not just the AI part of it, and
the UX layer added helpers there for the same reason the fakes live there: a
protection everybody has to remember is one somebody forgets.
`resourceSearchDefects()` judges every resource definition's `searchable()`,
`sortable()` and `recordLabel()` against the real schema
([[architecture/six-method-spine]]); `motionStyles()` runs the shipped motion
module under Bun with `matchMedia` answered both ways; `checkoutCopy()` builds
the repository copy the generator and install wizard tests work in
([[domains/ux-motion-and-a11y]]); and `ownerBeforeOnboarding()`,
`uploadedImport()` and `runImport()` set up the two flows whose fixtures are
awkward enough that each test would otherwise invent its own
([[domains/ux-onboarding]], [[domains/ux-import-and-uploads]]).

The controls being graded are [[domains/ai-injection-defense]] and the verticals
in [[domains/ai-agents-and-tools]].
