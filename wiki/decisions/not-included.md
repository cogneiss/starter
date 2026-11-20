---
title: Not included
status: current
supersedes: []
code_refs:
    - FEATURES.md
    - todo/specs/theming-system.md
    - app/Support/ResourceQuery.php
    - app/Ai/ConfirmableActions.php
    - app/Ai/Middleware/RecordAudit.php
    - .ai/rules/database.md
    - config/database.php
    - tests/Unit/Conventions/SchemaConventionTest.php
    - app/Concerns/LowercasesEmail.php
    - database/migrations/0001_01_01_000000_create_users_table.php
updated: 2026-11-20
---

# Not included

Absent on purpose, so a gap can be told from a decision. `FEATURES.md` carries the
canonical list; this page is the same content with the reasoning kept next to the
architecture it belongs to.

## Product surfaces

Billing. An admin panel and a token-auth read API used to sit on this list; the
platform ops layer shipped both, so what remains absent is a _write_ API, the
read surface at `/api/v1` is deliberately read-only ([[operations/ops-admin-area]],
[[operations/ops-read-api]], [[operations/ops-api-tokens]]).

Two entries left this list with the UX layer. Localization shipped, one server
side set of translation files, a supported-locale list and a key parity test
([[domains/ux-i18n]]). So did the upload surface, though not as a general file
manager: uploads exist to feed a bulk import, are quarantined until a scanner
returns clean, and expire if nobody promotes them ([[domains/ux-import-and-uploads]]).
A media library is still absent.

Announcements are in, as banners rather than a messaging product: scheduled,
per-organization or global, dismissed for good per member, and written from the
admin area (`FEATURES.md`, [[operations/ops-admin-area]]).

Backup and generated API docs are absent. The report lists them as optional
modules for regulated or protocol surfaces, which the kit does not target.

Filament is absent. The admin area already serves platform operations on the
same Inertia stack ([[operations/ops-admin-area]]); a second UI framework would
double the component layer to maintain.

## Tenancy and access

| Skipped                                     | Why                                                                                          |
| ------------------------------------------- | -------------------------------------------------------------------------------------------- |
| Host classifier (apex vs organization root) | Only makes sense with subdomains, add it with the subdomain resolver                        |
| Custom domains with on-demand TLS           | Depends entirely on the deploy target                                                        |
| Self-serve role builder UI                  | Most products need three fixed roles; `PermissionCatalog` is the data such a UI would render |
| Plan catalog and seat quotas                | Billing-shaped                                                                               |
| SAML / OIDC drivers                         | Heavy dependencies and per-provider debugging; a new login flow follows the rules on `SocialAuthController` |
| Access requests, cross-organization invites | Marketplace-shaped rather than universal                                                     |
| Device fingerprinting                       | Privacy-hostile, and in practice serves marketing attribution rather than authentication     |
| Postgres row-level security                 | Optional second layer behind the query-level scopes; the scopes already fail closed          |
| SSO and SCIM                                | Optional module; the report points at a WorkOS driver, priced per connection                 |

See [[decisions/org-access]] and [[features/authentication]].

## CI

| Skipped                          | Why                                                                            |
| -------------------------------- | ------------------------------------------------------------------------------ |
| Test sharding across runners     | The suite runs in parallel in a couple of minutes                              |
| Lighthouse scores                | Scores swing with the runner, so the gate would flake and get ignored. Byte and query counts do not swing, so `bin/bundle-budget` and `tests/Feature/ListQueryBudgetTest.php` budget those |
| `composer-require-checker`       | Overlaps `composer-unused` and is noisy against a runtime-resolving framework  |
| envy (`.env` drift)              | `.env.example` and `app:doctor` cover it; the check fires on unrelated changes |
| Dropping `jscpd`                 | Stays as the duplicate-code gate; its overlap with the other gates was not measured |

See [[decisions/ci-quality]].

## The resource spine

Cut back because the pattern pays off with several consumers reading one adapter.
Still out: `actions()`/`actionSchemas()`, the `ApiExposable` REST surface, the
resource loom, the AI presentation manifest and cheatsheet parity CI. Each reason
is in `FEATURES.md`; the shape is [[architecture/six-method-spine]] and
[[decisions/resource-spine]].

The UX layer supplied the consumers the cut was waiting for, so four of the
entries above are now shipped rather than skipped:

| Was skipped                                   | What shipped instead                                                                                                                                                  |
| --------------------------------------------- | --------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| `searchQuery()` / Scout / a generic `/search` | A scoped `search` endpoint over the registry, a word-prefix match through `ResourceQuery` (full-text on Postgres, `like` elsewhere) and still driver-free, the Scout decision is deferred, not made ([[domains/ux-search-and-palette]]) |
| ⌘K palette                                    | The client half of that endpoint, keyboard-first ([[domains/ux-search-and-palette]])                                                                                  |
| `visibleTo()` / `scopeFilter()` / `find()`    | Not resurrected as adapter methods. The list kit filters at the query level and lets the policy answer the rest ([[domains/ux-list-kit]])                             |
| The motion layer                              | A token module the components read, honouring `prefers-reduced-motion` ([[domains/ux-motion-and-a11y]])                                                               |
| Guard G6 (precognition on form routes)        | `tests/Feature/PrecognitionParityTest.php`, walking the router rather than the filesystem ([[domains/ux-forms-precognition]])                                         |

The third row is the one worth reading twice. The original reason for cutting
`visibleTo()`, _policies already answer that question, and duplicating the rule
in an adapter is two places to get it wrong_, held, so search and lists got a
scope at the query level and a policy check on top, and no third copy of the
rule in an adapter method.

## The AI layer

Shipped as a layer, not a product. `FEATURES.md` lists what was left out and
this is the reasoning:

| Skipped                                              | Why                                                                                                         |
| ---------------------------------------------------- | ----------------------------------------------------------------------------------------------------------- |
| A chat product                                       | Threads, history, sharing and moderation are a product; blocks, tools and confirm tokens are its foundation |
| Billing for AI spend                                 | Billing-shaped, the credit ledger and `ai:usage` are the meter such a module would read                    |
| Images, speech, transcription, provider file storage | Supported by the SDK, but each brings storage, moderation and a second provider account                     |
| Reranking                                            | Pays off against a tuned corpus, and there is no corpus here to tune                                        |
| Provider-hosted vector stores                        | pgvector keeps the corpus inside the tenancy boundary the rest of the kit enforces                          |
| `Approvable` from laravel/ai                         | The propose-then-confirm token also covers MCP and needs no `Conversational` agent; `app/Ai/ConfirmableActions.php` |
| OpenTelemetry gen_ai exporter                        | Still in Development status upstream; Sentry already emits gen_ai spans ([[operations/ops-error-reporting]]) |
| Contextual retrieval                                 | Only vendor evidence for it; would be an opt-in flag |
| MCP spec 2026-07-28 features                         | laravel/mcp serves the version but still advertises dynamic client registration; stdio is enough for now |
| A prompt-injection classifier                        | No production path; deterministic controls (fencing, read-only tools, confirm tokens) stay instead |
| Durable workflows (`durable-workflow/workflow`)      | Queue plus Reverb already covers long runs; maturity was not the reason |
| Provider-hosted agents                               | Beta and not zero-data-retention eligible, so tenant data would leave the boundary |
| A2A, ACP, AP2, x402, WebMCP                          | No current need; watch only |

The last row is the one with teeth. Every other control in the layer assumes the
data never leaves a database this application scopes, see
[[domains/ai-retrieval]] and [[architecture/fail-closed-scoping]]. A hosted store
puts the corpus somewhere `BelongsToOrganization` cannot reach, so the boundary
would have to be re-implemented against someone else's filter API.

An earlier cut in the resource spine, `actions()` / `actionSchemas()`, listed
above as _only useful to an AI assistant layer that is not here_, is now half
answered: `app/Ai/ConfirmableActions.php` is the registry of writes an agent may
propose ([[domains/ai-confirm-tokens]]). It is explicit rather than derived from
an adapter, because a write an agent can reach is worth listing by hand.

## Developer tooling

Three language-level code graphs (graphify, code-review-graph and gitnexus) were
rebuilt after every commit until 2026-10-29. They were dropped for lack of
evidence that they made agents more accurate here; `FEATURES.md` now lists
Laravel Brain as the only code graph ([[architecture/graph-before-grep]]).

Also absent. The `@laravel/stream-react` plus Streamdown chat client waits on a
spike, since it is pre-1.0 and has no confirmed Inertia 3 fit. Chisel is
pre-1.0 and only strips optional modules for forks. Inertia DevTools is not in
the 3.11 release notes and Live Props has no screen that needs it. Instant
visits have no call site yet; adopt them on the first page that ships a
loading skeleton. Promptfoo, Braintrust and a Node
sidecar are not part of the kit: the Pest evals plugin and `laravel/ai` cover
what they would add.

## Database design

Four things a schema review might expect are absent. `.ai/rules/database.md`
states each one as a rule.

No `timestampTz` column. A probe on PostgreSQL saved the same instants into a
plain `timestamp` column and a `timestampTz` column through Eloquent. With the
session pinned to UTC, both columns returned the same instant for a Carbon in
UTC. With the session left at the server default, `Asia/Kolkata` on the machine
that ran the probe, the `timestampTz` column came back 5 hours 30 minutes off
and a `useCurrent()` default on the plain column was 19,800 seconds off. So the
pin is what keeps a time correct, and `config/database.php` sets it on the
PostgreSQL, MySQL and MariaDB connections. The MySQL value is the offset
`+00:00`, because a named zone needs time zone tables that a server may not
have loaded. The type also means something on PostgreSQL only: the MySQL and
SQLite grammars build a plain timestamp for it. A Carbon in another zone lost
its instant on both column types, pinned or not, because Eloquent saves the
wall-clock time and drops the zone. The rule answers that with `->utc()`.
Nothing here runs MariaDB, so its pin is untested.

No check constraint on a value set. A status is a string column with an enum
cast, since a new value would otherwise need a constraint migration on every
engine. A check constraint is allowed for a rule that can never change. The
kit's schema has one such rule, on `email`, described below. PostgreSQL and
MySQL accepted a constraint added with `alter table` and refused a bad row.
SQLite refused the statement, so SQLite goes without.

No partial unique index that frees a soft-deleted value. A deleted
organization keeps its slug, so a unique index on a soft-deleting table stays
plain, and `tests/Unit/Conventions/SchemaConventionTest.php` fails on a
`Rule::unique` that skips deleted rows. A partial unique index is still the
tool for a rule that binds only some rows, such as one pending invitation per
email and organization.

No engine feature folds the case of an email address. PostgreSQL and SQLite
compare text by case and MySQL's default collation does not, so an address
stored as typed is two rows on two engines and a unique violation on the
third. The kit stores the address in lower case instead.
`app/Concerns/LowercasesEmail.php` lower-cases `email` on every Eloquent write
to the models that have the column, a case in the convention test fails for a
model that leaves the trait out, and each lookup lower-cases what it was given
before it queries ([[features/authentication]]). Each alternative lost for its
own reason. A `citext` column exists on PostgreSQL only. A
functional unique index on `lower(email)` stops two rows that differ by case,
but the stored value stays mixed, so every lookup must wrap the column in
`lower()` and one that forgets finds nothing. A collation change is a
different statement on each engine, and SQLite's `NOCASE` folds ASCII letters
only.

A query builder write goes past the trait, so a check constraint named
`<table>_email_lowercase` backs it on PostgreSQL and MySQL. The migration that
creates the table adds it, and
`database/migrations/0001_01_01_000000_create_users_table.php` is the pattern.
The constraint refuses an ASCII capital. It is not `email = lower(email)`,
because MySQL's default collation makes that comparison true for every value.
MySQL gets `not regexp_like(email, '[A-Z]', 'c')` and PostgreSQL gets
`email !~ '[A-Z]'`. SQLite cannot add a constraint to an existing table and
has none. `tests/Unit/Conventions/SchemaConventionTest.php` writes a capital
past the model to each table on PostgreSQL, and fails for a table that has the
column and no constraint. No standing test proves that MySQL refuses a
capital. A database migrated before the constraint existed has none until it
is rebuilt. `select count(*) from users where email <> lower(email)` counts
the rows there that still hold a capital, and on PostgreSQL and SQLite such a
row stops matching a sign-in until it is lower-cased.

## Drafted, not shipped

`todo/specs/theming-system.md` is a design note for a theming system. It is a
spec, not code.
