---
title: Not included
status: current
supersedes: []
code_refs:
    - FEATURES.md
    - todo/specs/theming-system.md
updated: 2026-08-25
---

# Not included

Absent on purpose, so a gap can be told from a decision. `FEATURES.md` carries the
canonical list; this page is the same content with the reasoning kept next to the
architecture it belongs to.

## Product surfaces

Billing, an admin panel, a REST or GraphQL API with token auth, localization, and
any file upload UI (Laravel's local-disk `storage.local` routes exist, nothing is
built on them).

## Tenancy and access

| Skipped                                     | Why                                                                                          |
| ------------------------------------------- | -------------------------------------------------------------------------------------------- |
| Host classifier (apex vs organization root) | Only makes sense with subdomains — add it with the subdomain resolver                        |
| Custom domains with on-demand TLS           | Depends entirely on the deploy target                                                        |
| Self-serve role builder UI                  | Most products need three fixed roles; `PermissionCatalog` is the data such a UI would render |
| Plan catalog and seat quotas                | Billing-shaped                                                                               |
| SAML / OIDC drivers                         | Heavy dependencies and per-provider debugging; the `AuthDriver` seam is the hook             |
| Access requests, cross-organization invites | Marketplace-shaped rather than universal                                                     |
| Device fingerprinting                       | Privacy-hostile, and in practice serves marketing attribution rather than authentication     |

See [[decisions/org-access]] and [[domains/auth-drivers]].

## CI

| Skipped                          | Why                                                                            |
| -------------------------------- | ------------------------------------------------------------------------------ |
| Test sharding across runners     | The suite runs in parallel in a couple of minutes                              |
| Lighthouse / performance budgets | Scores swing with the runner, so the gate would flake and get ignored          |
| `composer-require-checker`       | Overlaps `composer-unused` and is noisy against a runtime-resolving framework  |
| envy (`.env` drift)              | `.env.example` and `app:doctor` cover it; the check fires on unrelated changes |

See [[decisions/ci-quality]].

## The resource spine

Cut back because the pattern pays off with several consumers reading one adapter,
and this kit has none yet: `searchQuery()`/Scout/a generic `/search`/⌘K palette,
`visibleTo()`/`scopeFilter()`/`find()`, `actions()`/`actionSchemas()`, the
`ApiExposable` REST surface, the resource loom, the AI presentation manifest,
cheatsheet parity CI, the motion layer, and guard G6 (precognition on form
routes). Each reason is in `FEATURES.md`; the shape is
[[architecture/six-method-spine]] and [[decisions/resource-spine]].

## The AI layer

Shipped as a layer, not a product. `FEATURES.md` lists what was left out and
this is the reasoning:

| Skipped                                              | Why                                                                                                         |
| ---------------------------------------------------- | ----------------------------------------------------------------------------------------------------------- |
| A chat product                                       | Threads, history, sharing and moderation are a product; blocks, tools and confirm tokens are its foundation |
| Billing for AI spend                                 | Billing-shaped — the credit ledger and `ai:usage` are the meter such a module would read                    |
| Images, speech, transcription, provider file storage | Supported by the SDK, but each brings storage, moderation and a second provider account                     |
| Reranking                                            | Pays off against a tuned corpus, and there is no corpus here to tune                                        |
| Provider-hosted vector stores                        | pgvector keeps the corpus inside the tenancy boundary the rest of the kit enforces                          |

The last row is the one with teeth. Every other control in the layer assumes the
data never leaves a database this application scopes — see
[[domains/ai-retrieval]] and [[architecture/fail-closed-scoping]]. A hosted store
puts the corpus somewhere `BelongsToOrganization` cannot reach, so the boundary
would have to be re-implemented against someone else's filter API.

An earlier cut in the resource spine — `actions()` / `actionSchemas()`, listed
above as _only useful to an AI assistant layer that is not here_ — is now half
answered: `app/Ai/ConfirmableActions.php` is the registry of writes an agent may
propose ([[domains/ai-confirm-tokens]]). It is explicit rather than derived from
an adapter, because a write an agent can reach is worth listing by hand.

## Drafted, not shipped

`todo/specs/theming-system.md` is a design note for a theming system. It is a
spec, not code.
