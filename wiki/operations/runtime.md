---
title: Runtime
status: current
supersedes: []
code_refs:
    - .env.example
    - config/database.php
    - config/queue.php
    - config/mail.php
    - config/inertia.php
updated: 2026-09-01
---

# Runtime

Postgres, database sessions, database queue, database cache and the log mailer
out of the box. Sessions, queue, cache and mail need no service beyond the
database, and every driver swaps through `.env`.

Postgres is the default rather than SQLite because retrieval runs on pgvector,
and a vector column is not something to discover you cannot have after building
on it ([[domains/ai-retrieval]]). `.env.example` keeps the SQLite pair commented
out two lines below `DB_CONNECTION=pgsql` for anyone who wants the file-less
version and no vector search.

Nothing else in the kit depends on Postgres, which is what `composer test:sqlite`
proves nightly: the whole suite minus the `pgvector` group, on SQLite in memory
([[operations/testing]]).

## Flags worth knowing on day one

| Variable                  | Default   | Effect                                                                           |
| ------------------------- | --------- | -------------------------------------------------------------------------------- |
| `ORGANIZATIONS_RESOLVER`  | `session` | How the active organization is picked ([[domains/organization-resolvers]])       |
| `ORGANIZATIONS_STRICT`    | `true`    | Fail closed when no organization is bound ([[architecture/fail-closed-scoping]]) |
| `INERTIA_ENCRYPT_HISTORY` | off       | Encrypt Inertia history state                                                    |
| `AI_FAKE`                 | `false`   | Answer every agent from a canned response, no provider call                      |
| `BROADCAST_CONNECTION`    | `null`    | Where broadcasts go; `reverb` turns live notifications on                        |

Broadcasting is off by default. `.env.example` carries the Reverb block —
`REVERB_APP_ID`, `REVERB_APP_KEY`, `REVERB_APP_SECRET`, host, port, scheme, and
the four `VITE_REVERB_*` mirrors the browser client reads — all blank. With
`BROADCAST_CONNECTION=null` the notification bell still works: notifications are
written to the database and the panel fetches them, so the websocket is the
upgrade rather than the requirement ([[domains/ux-realtime-notifications]]).

Third-party credentials are all optional: the app boots and the suite passes with
every social, mail and AI provider key blank ([[domains/auth-drivers]],
[[domains/ai-layer-overview]]). The same holds for `SENTRY_DSN`/`SENTRY_RELEASE`
and `POSTHOG_KEY`/`POSTHOG_HOST`: blank by default, and error reporting and
analytics both stay local (`NullErrorReporter`, `NullAnalyticsReporter`) until a
key is set. The AI quota and budget ceilings are `.env`
values too, and they apply whether or not a key is set
([[domains/ai-metering-and-quotas]]).

Essentials sets the rest of the runtime posture — strict models, automatic eager
loading, immutable dates, destructive commands blocked in production, forced HTTPS
outside local ([[architecture/better-defaults]]).
