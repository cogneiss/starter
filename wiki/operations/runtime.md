---
title: Runtime
status: current
supersedes: []
code_refs:
    - .env.example
    - config/database.php
    - config/queue.php
    - config/mail.php
    - config/ai.php
updated: 2026-11-15
---

# Runtime

Postgres, database sessions, database queue, database cache and the log mailer
out of the box. Sessions, queue, cache and mail need no service beyond the
database, and every driver swaps through `.env`.

Postgres is the default rather than SQLite because retrieval runs on pgvector,
and a vector column is not something to discover you cannot have after building
on it ([[domains/ai-retrieval]]). `.env.example` keeps the SQLite pair
(`DB_CONNECTION=sqlite`, `DB_DATABASE=:memory:`) commented out below the Postgres
block for anyone who wants the file-less
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
| `INERTIA_SSR_ENABLED`     | `true`    | Inertia server-side rendering; needs a separate render service to take effect (Inertia's own default config) |
| `AI_FAKE`                 | `false`   | Answer every agent from a canned response, no provider call                      |
| `AI_MODEL_CHEAP`, `AI_MODEL_SMART` | `claude-haiku-5-5`, `claude-sonnet-5-5` | Model behind each tier (`config/ai.php`)                  |
| `AI_MODEL_CHEAP_FAILOVER`, `AI_MODEL_SMART_FAILOVER` | `gpt-6-luna`, `gpt-6-astra` | Model of the failover provider for each tier (`config/ai.php`) |
| `BROADCAST_CONNECTION`    | `null`    | Where broadcasts go; `reverb` turns live notifications on                        |

Broadcasting is off by default. `.env.example` sets `APP_URL` to `http://localhost:8000`. It carries the Reverb block —
`REVERB_APP_ID`, `REVERB_APP_KEY`, `REVERB_APP_SECRET`, host, port, scheme, and
the four `VITE_REVERB_*` mirrors the browser client reads. The app id, key and
secret and the `VITE_REVERB_*` mirrors are blank; host, port and scheme are
preset to `localhost`, `8080` and `http`. With
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
