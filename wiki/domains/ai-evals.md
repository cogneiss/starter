---
title: AI evals
status: current
supersedes: []
code_refs:
    - tests/Pest.php
    - tests/Evals/InjectionDefenseEvalTest.php
    - tests/Evals/InvitationDrafterEvalTest.php
    - tests/Evals/DashboardBriefingEvalTest.php
    - .github/workflows/evals.yml
    - composer.json
updated: 2026-08-25
---

# AI evals

Two kinds of test exist here, and conflating them is how a suite ends up both
flaky and expensive.

## Tests: no provider, ever

The blocking suite never reaches a provider. `tests/Pest.php` fakes every agent
that ships with no scripted answer and calls `preventStrayPrompts()`, so a test
that forgets its own fake throws instead of dialling out. The protection is
central rather than per-test, because the per-test version is the one someone
forgets.

Tests prove the mechanics: the prompt was fenced, the tool asked the policy, the
token was single use. Whether a real model respects any of it is not something a
fake gateway can tell you.

## Evals: real providers, on a schedule

`tests/Evals/` is a separate suite, excluded from `composer test` and run by
`composer test:evals`. Its tests skip themselves when the fake gateway is
answering, so they are inert without keys. Three ship:

| Eval                        | Grades                                                     |
| --------------------------- | ---------------------------------------------------------- |
| `InjectionDefenseEvalTest`  | that a real model reads fenced organization data as data   |
| `InvitationDrafterEvalTest` | that drafted invitations say what they should              |
| `DashboardBriefingEvalTest` | that briefings are grounded in the figures they were given |

Cases live in `tests/Fixtures/Ai/*.json` alongside the prompt, provider and model
each was captured against, read by the `evalFixture()` and `evalCases()` helpers
in `tests/Pest.php`. Adding a case is a JSON edit, not a new test.

## Why they never gate a branch

`.github/workflows/evals.yml` runs weekly and on request, never on a pull
request, with `continue-on-error: true`. Evals cost money and are allowed to be
flaky in a way a test is not; a regression opens an issue for someone to read
rather than reddening a branch that did nothing wrong. The blocking gates are in
[[architecture/fast-blocking-gates]].

The controls being graded are [[domains/ai-injection-defense]] and the verticals
in [[domains/ai-agents-and-tools]].
