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
updated: 2026-08-24
---

# Runtime

SQLite, database sessions, database queue, database cache and the log mailer out
of the box. Clone and run: no Postgres, Redis or SMTP to install anywhere. Every
driver swaps through `.env`.

That default is a deliberate trade. It costs some production realism — hence the
nightly Postgres run, because SQLite and Postgres disagree about UUID keys, JSON
columns, unordered `ORDER BY` and case-sensitive `LIKE`
([[operations/testing]]).

## Flags worth knowing on day one

| Variable                  | Default   | Effect                                                                           |
| ------------------------- | --------- | -------------------------------------------------------------------------------- |
| `ORGANIZATIONS_RESOLVER`  | `session` | How the active organization is picked ([[domains/organization-resolvers]])       |
| `ORGANIZATIONS_STRICT`    | `true`    | Fail closed when no organization is bound ([[architecture/fail-closed-scoping]]) |
| `INERTIA_ENCRYPT_HISTORY` | off       | Encrypt Inertia history state                                                    |

Third-party credentials are all optional: the app boots and the suite passes with
every social and mail key blank ([[domains/auth-drivers]]).

Essentials sets the rest of the runtime posture — strict models, automatic eager
loading, immutable dates, destructive commands blocked in production, forced HTTPS
outside local ([[architecture/better-defaults]]).
