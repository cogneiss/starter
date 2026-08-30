# Features

What ships in this starter kit, split into what your users touch and what you
touch. Setup instructions live in [README.md](README.md).

## User-facing

### Authentication

| Feature               | Details                                                                                                                                                                                                    |
| --------------------- | ---------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| Registration          | Name, email, password. Sends a verification email and logs the user in.                                                                                                                                    |
| Password login        | Email and password, with "remember me" and rate limiting.                                                                                                                                                  |
| Social login          | Google, GitHub and Microsoft through Socialite. Off by default; an OAuth email that matches an existing verified account links to it instead of creating a second one, and an unverified match is refused. |
| Magic link            | Request a one-time login link by email. Tokens last 15 minutes, work once, and never reveal whether an address is registered.                                                                              |
| Passkeys              | Register and sign in with WebAuthn (Touch ID, Face ID, Windows Hello, hardware keys) via Fortify. Passwordless sign-in from the login page.                                                                |
| Two-factor auth       | TOTP with a QR code, confirmation step, and single-use recovery codes. Enabling it requires a password confirmation.                                                                                       |
| Password reset        | Emailed reset link with the usual token expiry and throttling.                                                                                                                                             |
| Email verification    | Signed verification links, resend endpoint, and a `verified` guard on the dashboard.                                                                                                                       |
| Password confirmation | Re-prompts for the password before sensitive settings changes.                                                                                                                                             |

Every path respects two-factor: magic links and passkeys hand a 2FA user to the
challenge screen instead of logging them straight in. An organization can also
require two-factor for everyone in it — members without it are held on the setup
screen until they enable it.

### Account settings

- **Profile** — update name and email; changing the email drops verification and
  requires re-verifying.
- **Password** — update with current-password confirmation, throttled.
- **Passkeys** — list, name, and delete registered credentials.
- **Two-factor** — enable, confirm, view and regenerate recovery codes, disable.
- **Appearance** — light, dark, or follow the system, persisted in a cookie so
  the server renders the right theme on first paint (no flash).
- **Browser sessions** — see every browser signed in to the account, with device
  and last activity, and log the others out with one password-confirmed click.
- **Login history** — the last ten sign-in attempts, successful and failed, kept
  for 90 days and then pruned.
- **Delete account** — password-confirmed, permanent.

### Organizations

Every user belongs to at least one organization, and everything they create
lives inside it.

- **Switcher** — users in more than one organization switch from the sidebar;
  the switcher stays hidden for anyone in a single organization, so a solo
  sign-up never sees it. Signing up on your own creates a personal organization
  behind the scenes, which is why the kit suits B2C products as well as B2B.
- **Members** — a list of everyone in the organization with their role and
  status, plus removal.
- **Invitations** — invite by email, resend, revoke. Accepting an invitation
  joins the organization; the last active owner cannot be removed or demoted.
- **Roles** — owner, admin and member ship as templates, cloned into each new
  organization so they can be edited per organization.
- **Suspension** — suspend a member without deleting anything, then reactivate
  them later. A suspended member keeps their data and loses their access.
- **Organization settings** — name, and a switch that requires two-factor
  authentication from every member.
- **Impersonation** — a super admin can sign in as a user to reproduce a
  support issue. Every session is written to an audit log, and destructive
  actions are blocked while impersonating.

### Pages

A public welcome page at `/`, a dashboard behind auth and email verification,
and the auth and settings screens listed above. Everything else is yours to
build.

### Interface

- Inertia v3 + React 19 single-page experience with server-side routing.
- Two app shells (sidebar or header nav) and three auth layouts (simple, card,
  split) — swap by changing one import.
- shadcn-style component set on [Base UI](https://base-ui.com) primitives
  (`base-nova`), so keyboard navigation, focus management, and ARIA come for
  free.
- Tailwind v4, responsive down to mobile, collapsible sidebar with persisted
  state.
- Password fields have a show/hide toggle with a proper `aria-label`.
- Semantic value components in `resources/js/components/value` for the values
  every app renders the same way and formats differently on every page: `Money`,
  `Percent`, `DateValue`, `RelativeTime`, `BooleanPill`, `StatusBadge`,
  `EmailValue`, `UrlValue`, `PhoneValue`, `TagList`, `CodeValue`, `LongText`, and
  the `EmptyValue` the rest fall back to. Hand any of them `null` and you get one
  em-dash with a screen-reader label, never `NaN`, `null` or `Invalid Date`.
  Formatting goes through the platform's `Intl` APIs, so currency, number and
  date rendering follow the locale instead of a hand-rolled helper. `/_value-gallery`
  renders all of them, with a value and without, outside production.
- Breadcrumbs, flash toasts (Sonner, flashed from the server with
  `Inertia::flash('toast', ...)`), loading spinners on every submit, and inline
  field errors.
- Small hooks for the fiddly parts: clipboard copy for recovery codes, initials
  for avatars, mobile breakpoint and navigation, current URL, appearance.
- SSR entry point (`resources/js/ssr.tsx`) wired, built with
  `bun run build:ssr`.
- Preload `Link` headers on responses, and `appearance` and `sidebar_state`
  cookies left unencrypted so the server can read them before hydration.

## Developer experience

### Architecture

- **Actions** — business logic lives in single-method `app/Actions` classes,
  callable from controllers, jobs, commands, or MCP tools.
- **Cruddy by design** — controllers stay at `create`/`store`/`edit`/`update`/
  `show`/`destroy`, one resource each. An architecture test forbids controllers
  from being used anywhere but routes.
- **Resource spine** — one adapter per user-facing model in
  `app/Resources/Definitions`, auto-discovered by `ResourceRegistry` (cached with
  `php artisan resource:cache`). Six methods, no more: `key()` and `label()` name
  the resource, `model()` and `dataClass()` say which Eloquent model and which
  Data class it maps to, `policy()` points at its policy, and `url(Model)` builds
  the link to a record from Wayfinder helpers. `url()` is the one that earns its
  keep: it stops `switch (result.type)` branching from ever appearing on the
  frontend. The `searchQuery()`, `visibleTo()`, `actions()` and API-exposure
  methods the pattern usually carries are deliberately absent — they exist to
  serve a search index, an assistant layer and a REST surface, none of which this
  kit ships. The registry is the seam they would attach to.
- **Form requests** for every write, with a reusable `ValidEmail` rule.
- **UUID primary keys** on users, hidden sensitive attributes, and typed
  `@property-read` docblocks on the model.
- `declare(strict_types=1)` and `final` classes everywhere, enforced by Pest's
  strict architecture preset.

### Multi-tenancy

- `OrganizationContext` is a singleton holding the current organization.
  `runAs()` binds one for the duration of a closure, which is how tests and
  queued jobs set the tenant.
- `BelongsToOrganization` adds a global scope plus automatic `organization_id`
  filling. Scoped models are filtered everywhere, including relations.
- **Fail-closed by default** — with no organization bound, a scoped query throws
  `OrganizationContextMissing` rather than returning every row.
  `ORGANIZATIONS_STRICT=false` downgrades that to an empty result, which is only
  useful while migrating an existing database.
- Three resolvers ship behind one interface: `session` (default), `subdomain`,
  and `single` for apps that will only ever have one organization. Swap with
  `ORGANIZATIONS_RESOLVER`.
- Jobs that touch scoped models implement `OrganizationAware` and return
  `WithOrganizationContext` from `middleware()`, so the tenant survives the
  queue instead of leaking from whichever job ran before.
- Escaping the scope is possible but deliberate: `withoutOrganizationScope()`.

### Authorization

- **Two gates, always** — a policy _and_ a permission. The policy answers "is
  this user allowed near this record", the permission answers "does their role
  include this verb". A test enforces that every policy method checks both.
- RBAC on [spatie/laravel-permission](https://spatie.be/docs/laravel-permission)
  with organization-scoped teams, so the same user can be an owner in one
  organization and a member in another.
- **Permission catalog** — `PermissionCatalog` is the single list of every
  permission, named `<resource>.<verb>`. An unregistered or misnamed permission
  fails the test suite instead of silently denying access.
  `php artisan app:sync-permissions` writes the catalog to the database.
- **Role templates** — `RoleTemplate` holds the global blueprints, cloned into
  each organization when it is created.

### Feature flags

- [Pennant](https://laravel.com/docs/pennant) with a `KnownFeatures` registry:
  every flag is declared there before use, so a typo'd key is a failing test
  rather than a silent `false`.
- Per-organization overrides with an optional expiry, cleaned up by
  `php artisan app:expire-feature-overrides`.
- Impersonation and social login both ship behind flags, off by default.

### Type safety

- PHPStan (Larastan) at **level max** across `app`, `config`, `database`,
  `routes`, `public`, and `bootstrap`.
- **100% type coverage** gate — every parameter, property, and return type is
  annotated or the build fails.
- TypeScript checked with `tsc --noEmit`, plus type-aware linting in Vite+.
- **Wayfinder** generates typed TS functions from PHP routes and controllers, so
  a renamed route breaks the frontend build instead of production.
- **Typed payloads**, not just typed routes. Every Inertia payload is a
  `spatie/laravel-data` class in `app/Data` carrying `#[TypeScript]`;
  `spatie/laravel-typescript-transformer` turns those into TS interfaces. Pages
  import the generated type, so a renamed or removed field breaks `tsc` instead
  of rendering `undefined`. Run `composer typescript:generate` after touching
  `app/Data`. `resources/js/types/generated.d.ts` is committed on purpose — CI,
  the editor and a fresh clone all see the types without a build step, and a
  stale file shows up as a diff in review (`app:doctor` checks for exactly that).

### Convention guards

Three architecture tests in `tests/Unit/Conventions/ConventionTest.php` that
fail the build when a new file skips a convention:

- **G1** — every model in `app/Models` has a factory.
- **G4** — every class in `app/Data` carries `#[TypeScript]`, and so does every
  `dataClass()` a registered adapter returns. Without it a Data class silently
  never reaches the frontend types.
- **G5** — every model has a resource adapter. A new model fails CI until you run
  `app:make-resource` or write down why it is exempt.

Exceptions live in `config/conventions.php`, keyed by class with a **reason
string** as the value rather than a flat list, so a reviewer can see why one
exists and a stale one is obvious. Add the exception; never weaken the guard.
Each failure message names the exact command that fixes it.

### Testing

- Pest 5 with 493 tests covering every controller, action, rule, and middleware.
- Coverage gate at `--exactly=100.0` — not a minimum, an exact match, so dead
  code fails the build too.
- Architecture presets (php, strict, laravel, security) catch `dd()` leftovers,
  loose comparisons, non-final classes, and insecure function calls.
- Browser tests through `pest-plugin-browser` and Playwright, with screenshots
  uploaded on CI failure.
- Tests run in parallel, with frozen time, faked sleep, and stray HTTP and
  process calls blocked by default.
- Every page the kit ships is run through axe-core at the strictest level
  (`assertNoAccessibilityIssues(level: 3)`, so minor and best-practice
  violations fail too) — a starter kit's accessibility defects are inherited by
  every application built on it.
- Test Impact Analysis enabled locally — only the tests your change touches run.
  It is deliberately local only (`pest()->tia()->locally()`): CI has no reason
  to trust a map it did not build, so every CI job runs the full suite.
- `composer test:sqlite` reruns the whole suite on SQLite nightly, minus the
  `pgvector` group. Postgres is the default everywhere, so the scheduled run
  proves a fork that drops it still gets everything except vector search. The
  two engines disagree on UUID keys, JSON columns, unordered `ORDER BY` and
  case-sensitive `LIKE`; a failure opens an issue.
- Mutation testing weekly. Coverage says a line ran; the mutation score says a
  test actually failed when that line was broken. It reports, it never blocks —
  gating on the score teaches people to write tests that satisfy the mutator.
- `UserFactory` ships `unverified()` and `withoutTwoFactor()` states, and
  `db:seed` creates `test@example.com` / `password` for local sign-in.

### CI and quality gates

One `setup` job installs Composer and Bun dependencies, builds the frontend and
uploads the build as an artifact. Everything after it downloads that artifact
and runs in parallel: `tests` (the full `composer test` gate), `security`
(gitleaks, `composer audit`, `bun audit`), `static` (dead code, unused Composer
packages, knip) and `a11y` (the axe-core browser suite). A second job costs a
job slot, not a second install.

| Runs                          | What                                                                                      |
| ----------------------------- | ----------------------------------------------------------------------------------------- |
| Blocking, on every PR         | `composer test`, gitleaks, dependency audits, dead code, unused deps, knip, accessibility |
| Scheduled, reports only       | Postgres nightly, mutation score weekly, TIA baseline, SBOM on release                    |
| On a push to a branch in-repo | the autofix bot — formats and commits back, skipped on forks                              |

The split is deliberate: **a gate that blocks a pull request must be fast,
deterministic and about the diff; everything else runs on a schedule and
reports.** Blocking gates people cannot predict get routed around — branch
protection gets loosened, or the team learns which rerun makes red go away.
Mutation testing, the Postgres run and the SBOM are scheduled for that reason,
not because they matter less.

Never lower a threshold to make a gate pass. Baseline the finding with its
reason, or fix the code.

### Tooling

- **Pint** for PHP formatting, **Rector** for automated upgrades and refactors.
- **Vite+** (Rolldown) for the frontend, with oxlint and oxfmt handling lint and
  format, Tailwind class sorting, and import sorting.
- **React Compiler** enabled via Babel — no manual `useMemo` and `useCallback`.
- `components.json` configured for the shadcn CLI (new-york style), so
  `bunx shadcn@latest add ...` drops components straight into
  `resources/js/components/ui`.
- **Laravel Boost** MCP server (including browser log capture at
  `_boost/browser-logs`) plus per-domain skills and guidelines in `.ai/`, so AI
  agents get version-correct docs and this project's conventions. Ten skills
  ship (Laravel best practices, Fortify, Inertia+React, Wayfinder, Pest,
  Tailwind, Pennant, Socialite, gitnexus, infer-conventions), mirrored into
  `.agents/`, `.claude/`, `.cursor/`, `.github/`, and `.junie/`.
- `AGENTS.md`, `CLAUDE.md`, `GEMINI.md`, `.junie/guidelines.md`, and
  `opencode.json` ship configured. `boost.json` records which agents and skills
  are installed; `php artisan boost:install --guidelines --skills` regenerates
  them all. Boost no longer emits `GEMINI.md`, so keep it in sync by hand
  (`cp AGENTS.md GEMINI.md`) after a Boost update.
- `.ai/rules/` holds the committed, path-scoped project rules; `.ai/rules/index.md`
  maps globs to rule files, and Boost's `record-rule` tool adds to it.
- **Code knowledge graphs** wired into a post-commit hook, so AI agents answer
  structural questions from an index instead of reading dozens of files — see
  below.
- **gitleaks** scans every pull request, and the full history on a schedule, so
  a key committed once does not sit in the log unnoticed.
- **`composer audit`** and **`bun audit`** fail the build on a known
  vulnerability in a dependency, both halves of the stack.
- **Dead-code detection** (`composer test:dead-code`), **`composer-unused`**
  (`composer test:deps`) and **knip** (`composer test:knip`) keep unreachable
  PHP, unused Composer packages and orphaned frontend files from accumulating.
  Each records its accepted findings in one place — `phpstan-deadcode-baseline.neon`,
  `composer-unused.php`, `knip.json` — with a comment saying why, rather than
  being silenced by loosening the tool.
- **An autofix bot** — `lint-autofix.yml` runs the formatters on a branch push
  and commits the result back, so formatting never costs a review round. It is
  hard-skipped on forks; that guard is a security control, do not touch it.
- **Pail** for readable log tailing during development.
- `php artisan make:action` scaffolds an action; Essentials also ships its own
  Rector and Pint commands.
- **`php artisan app:make-resource <Name>`** scaffolds a whole resource from
  `stubs/resource/*.stub`: model, migration, factory, Data class, policy, action,
  form request, controller (`create`/`store` only), resource adapter, the Inertia
  create page, the route line, the permission entry, and four tests — model,
  action, controller and Data. It generates less than a full CRUD set on purpose,
  because everything it does generate passes `composer test` unedited, coverage
  gate included. `--dry-run`, `--force` and `--no-migration` are there when you
  need them.
- **`php artisan app:doctor`** answers the onboarding questions in one command:
  PHP version against `composer.json`, required extensions, a coverage driver,
  `.env` and `APP_KEY`, database reachability, pending migrations, `bun`,
  `node_modules`, the Vite manifest, stale generated TypeScript, and writable
  `storage`/`bootstrap/cache`. Every failure prints the command that fixes it.
  `--json` for scripts; exit code 1 if anything failed.

### Code knowledge graphs

Four indexes of this codebase, rebuilt automatically after every commit by
`.githooks/post-commit` (wired up by `composer install`, nothing to run by hand).
They exist to cut the tokens an AI agent burns rediscovering structure — a
question like "what breaks if I change this" is one command instead of a
file-by-file crawl.

| Tool                                                                | Answers                                                                                                                | Ships as                         |
| ------------------------------------------------------------------- | ---------------------------------------------------------------------------------------------------------------------- | -------------------------------- |
| [Laravel Brain](https://github.com/laramint/laravel-brain)          | Routes, controllers, models and relationships, events, listeners, jobs, middleware, policies, DB operations per method | `require-dev`, already installed |
| [graphify](https://pypi.org/project/graphifyy/)                     | "How does X relate to Y", where a concept lives                                                                        | optional, `pip`                  |
| [code-review-graph](https://github.com/tirth8205/code-review-graph) | Blast radius, callers, dead code, large functions                                                                      | optional, `pip`                  |
| [gitnexus](https://github.com/looptech-ai/gitnexus)                 | Execution flows, a symbol's callers and callees, diff-to-flow mapping                                                  | optional, `npm`                  |

Laravel Brain is the one to reach for first: the other three are language-level
and see symbols and calls, while only Laravel Brain knows that a route binds to a
controller or that an event has listeners. Its context exporter returns a
budgeted slice of a single request path —

```bash
php artisan brain:export-context --route=/settings/profile --budget=2000
```

— giving the route's middleware, call chain, complexity hotspots and queries in a
few hundred words, instead of opening the controller, form request, action and
model. There is also an interactive graph viewer at `/_laravel-brain` in dev.

`.ai/rules/general.md` tells every AI agent which tool answers which question, so
this routing costs nothing until a query is actually run. The three optional
tools degrade gracefully — the hook detects a missing binary and skips it. None
of the four run as an MCP server on purpose: an always-on MCP server re-sends its
tool schemas every conversation turn whether you use it or not, which is the
opposite of the point. Setup details are in [SETUP.md](SETUP.md).

### Agent and AI-native DX

Five pieces, each aimed at the same problem: an agent opening this repo for the
first time has no idea what is load-bearing, and the usual answer — a long
`CLAUDE.md` nobody maintains — goes stale in a week.

**One source for the agent guidelines.** `AGENTS.md`, `CLAUDE.md`, `GEMINI.md`,
`.cursor/rules/laravel-boost.mdc` and `.junie/guidelines.md` are all generated
from `.ai/guidelines/*.blade.php` by `php artisan boost:install --guidelines
--skills`, which runs on every `composer update`.
`tests/Feature/Docs/GuidelinesAreCurrentTest.php` fails when a generated file
drifts from its source, so the five files agree with each other by construction
instead of by discipline. Edit the Blade source; never the outputs.

**A wiki with a blocking lint.** `wiki/**` holds the long explanations that do
not fit in a terse rule — how a thing works, why it is shaped that way, what was
rejected. Every page carries frontmatter listing the files it describes in
`code_refs`, and `php artisan wiki:lint` (`composer test:wiki`) fails the build
on five conditions, the sharp one being staleness: change a file a page cites
without updating the page and CI goes red. That makes documentation drift a test
failure rather than a good intention.

**A documentation worklist.** `php artisan wiki:audit` writes
`wiki/_meta/audit.json`: pages gone stale, application files no page mentions,
pages whose refs have all been deleted. `.githooks/post-commit` refreshes it
after every commit and `php artisan app:doctor` prints the counts. The
`/document` slash command (`.claude/commands/document.md`) reads that worklist,
rereads the code and rewrites the pages. No language model runs in CI: the
pipeline produces the worklist, a developer's machine writes the prose, so there
is no API key in the build and no per-push cost.

**Three first-party skill packs.** `.ai/skills/resource-spine`,
`.ai/skills/org-access` and `.ai/skills/testing-gates` are procedures — do X,
then Y, in this order — loaded only when the domain is entered, and published
into `.claude/`, `.agents/`, `.cursor/`, `.junie/` and `.github/` by the same
`boost:install` run. They cover the three places an agent reliably gets this kit
wrong: skipping the resource generator, writing a query with no organization
bound, and reaching for the wrong test command. The layering rule is in
[`wiki/index.md`](wiki/index.md) — rules are normative and terse, skills are
procedure, the wiki carries the reasoning, and a fact lives in exactly one of
them.

**Zero-key boot.** `tests/Feature/ZeroKeyBootTest.php` blanks every optional
third-party credential through the config layer and requests every page the
router knows about, guest and authenticated. A fresh clone with an empty `.env`
boots and renders; social login being off is a feature switched off, not a
stack trace. `app:doctor` reports the same split — required-and-missing is an
error, an absent credential is a feature listed as off and never changes the
exit code.

### AI layer

A product layer built on `laravel/ai`, living in `app/Ai/` and wired so the
tenancy, authorization and metering rules the rest of the kit enforces apply to
an agent too. It boots with zero keys: with no provider credential configured
every agent is answered by the fake gateway, so a fresh clone renders the AI
pages and runs the whole suite without an account anywhere.

**Agents inherit the pipeline.** Every vertical implements `OrganizationScoped`
and runs four middleware in `app/Ai/Middleware` — a quota check, an untrusted
input fence, a topic filter, and an audit record. `tests/Unit/ArchTest.php` fails
the build when a new agent skips either interface, so the pipeline is inherited
rather than remembered. Agents pick a tier (`cheap` or `smart`), never a
model name, and `config/ai.php` maps each tier to a provider and model.

**Tools ask the policy.** `app/Ai/Tools` reads through the resource registry —
list records, show one, search the organization's documents, remember a fact —
and every tool calls `authorizeFor()` before it answers, so an agent can reach
exactly what the person driving it can reach. Writes are never performed by an
agent: `ProposeAction` returns a single-use confirm token, and the write happens
when a human confirms it.

**Injection defence.** Customer text reaches a prompt fenced by
`UntrustedContent`, never inline, and the egress allowlist bounds what a tool may
fetch. The controls have tests that go red when the control is removed, not tests
that only assert the happy path.

**Metered.** Every run writes an audit row with tokens and cost.
`php artisan ai:usage` and the organization usage page read the same action, and
quotas (per hour, per day, per month of spend) refuse a run before it costs
anything.

**Rendered as blocks.** Agents answer with typed blocks — text, markdown, table,
list, metric, form, confirm — rendered by React components, so an answer is a UI
rather than a wall of prose.

**MCP.** `app/Mcp` exposes the same three tools over the Model Context Protocol
for a local client, delegating to the AI tools rather than reimplementing their
checks. It ships disabled in `.mcp.json`.

The long version, one page per piece, starts at
[`wiki/domains/ai-layer-overview.md`](wiki/domains/ai-layer-overview.md).

### Better defaults

Courtesy of [Essentials](https://github.com/nunomaduro/essentials), on by
default: strict models (no lazy loading, no silent attribute discards),
automatic eager loading, immutable dates, unguarded models, aggressive
prefetching, destructive command prohibition in production, forced HTTPS
outside local, a default password for factories, blocked stray HTTP requests,
and faked sleep in tests.

### Runtime

SQLite, database sessions, database queue, database cache, and the log mailer
out of the box — clone and run, no services to install. Swap any driver through
`.env`. Inertia history encryption is one flag away
(`INERTIA_ENCRYPT_HISTORY=true`).

### Commands

Blocking gates — these run on every pull request, and all of them have to be
green:

| Command                   | What it does                                                           |
| ------------------------- | ---------------------------------------------------------------------- |
| `composer test`           | Lint, type coverage, PHPStan, and the test suite at `--exactly=100.0`. |
| `composer test:a11y`      | axe-core at level 3 over every page the kit ships.                     |
| `composer test:audit`     | `composer audit` plus `bun audit`.                                     |
| `composer test:dead-code` | PHPStan's dead-code rules over `app/`.                                 |
| `composer test:deps`      | `composer-unused` — Composer packages nothing requires.                |
| `composer test:knip`      | knip — unused frontend files, exports and dependencies.                |
| `composer test:wiki`      | `php artisan wiki:lint` — the wiki rules, all five blocking.           |

Scheduled — these report, they never block:

| Command                | What it does                                                                                       |
| ---------------------- | -------------------------------------------------------------------------------------------------- |
| `composer test:sqlite` | The whole suite against SQLite instead of PostgreSQL, minus the vector-search tests. Runs nightly. |
| `composer test:mutate` | Mutation score over `app/`. Runs weekly.                                                           |
| `composer test:evals`  | Grades prompts against a real provider. Weekly and on request; skips itself with no key set.       |
| `composer sbom`        | Write `sbom.json` (CycloneDX). Attached to each release.                                           |

Local loops:

| Command                                        | What it does                                                                  |
| ---------------------------------------------- | ----------------------------------------------------------------------------- |
| `composer setup`                               | Install, key, migrate, build. One shot from a fresh clone.                    |
| `composer dev`                                 | Server, queue worker, log tail, and Vite together.                            |
| `composer test:fast`                           | Parallel, compact, stops at the first failure. The one to run while working.  |
| `composer test:dirty`                          | Only the tests covering files you have edited.                                |
| `composer test:tia-seed`                       | Record the impact map `test:dirty` reads. Needs a coverage driver.            |
| `composer lint`                                | Rector, Pint, and the frontend formatter, applying fixes.                     |
| `composer update:requirements`                 | Bump PHP and JS dependencies to latest.                                       |
| `composer typescript:generate`                 | Rewrite `resources/js/types/generated.d.ts` from the `#[TypeScript]` classes. |
| `php artisan app:make-resource <Name>`         | Scaffold a model and everything around it, tests included.                    |
| `php artisan app:make-onboarding-step <Name>`  | Scaffold an onboarding step the registry picks up.                            |
| `php artisan app:doctor`                       | Check that this machine can run, test and build the app.                      |
| `php artisan ai:install`                       | Install the `vector` extension. A no-op away from PostgreSQL.                 |
| `php artisan resource:cache`                   | Cache the resource registry for production.                                   |
| `php artisan resource:clear`                   | Undo `resource:cache`.                                                        |
| `php artisan app:sync-permissions`             | Write the permission catalog to the database.                                 |
| `php artisan app:expire-feature-overrides`     | Drop feature overrides whose expiry has passed.                               |
| `php artisan brand:preview <primary> <accent>` | Print the palette two brand hexes derive, with every measured contrast ratio. |
| `php artisan wiki:lint`                        | The five wiki rules. What `composer test:wiki` runs.                          |
| `php artisan wiki:audit`                       | Rewrite `wiki/_meta/audit.json`, the worklist `/document` reads.              |
| `bun run knip:fix`                             | Apply knip's removals instead of only reporting them.                         |
| `bun run types`                                | Type-check the front end with `tsc --noEmit`, no build.                       |

CI runs all of the blocking gates on every push and pull request against `main`,
with Composer, Bun, Playwright, Rector, and PHPStan caches warm.

## Releases

Commits follow [Conventional Commits](https://www.conventionalcommits.org)
(`feat:`, `fix:`, `docs:`, `refactor:`, `test:`, `ci:`, `chore:`, and `!` or a
`BREAKING CHANGE:` footer for a breaking change). The `.githooks/commit-msg`
hook, wired up by `composer install`, rejects anything else before it reaches a
branch.

`release-please` reads those prefixes on every push to `main` and keeps a
release pull request open with the next version and the generated `CHANGELOG.md`
entry. Merging it tags the release. `feat:` bumps the minor, `fix:` the patch, a
breaking change the major — while the version is below 1.0.0 a breaking change
bumps the minor instead.

Each published release gets a CycloneDX SBOM (`sbom.json`) built from the lock
file and uploaded as a release asset, so a consumer can answer "does this ship
the vulnerable version of X" without cloning the repo.

## Not included

Deliberately absent, so you add what your product actually needs: billing, an
admin panel, a REST or GraphQL API with token auth, localization, and any file
upload UI (Laravel's local-disk `storage.local` routes exist, nothing is built
on them).

These were considered for the tenancy and access work and left out on purpose,
so you can tell a decision from a gap:

| Skipped                                            | Why                                                                                                                                     |
| -------------------------------------------------- | --------------------------------------------------------------------------------------------------------------------------------------- |
| Host classifier (apex vs organization root)        | Only makes sense if you use subdomains. Add it alongside the subdomain resolver if you turn that on.                                    |
| Custom domains with on-demand TLS                  | Depends entirely on the deploy target — Caddy, Laravel Cloud and Vercel all want something different.                                   |
| Self-serve role builder UI                         | Most products need three fixed roles. `PermissionCatalog` is the data such a UI would render, so it is there when you want one.         |
| Plan catalog and seat quotas                       | Billing-shaped; it belongs with whichever billing module you pick.                                                                      |
| SAML / OIDC drivers                                | Heavy dependencies and per-provider debugging. The `AuthDriver` seam is the hook — read the warning in its docblock before writing one. |
| Access requests and cross-organization invitations | Marketplace-shaped rather than universal.                                                                                               |
| Device fingerprinting                              | Privacy-hostile, and in practice it serves marketing attribution rather than authentication.                                            |

The same applies to the CI work — these were considered and left out:

| Skipped                          | Why                                                                                                                       |
| -------------------------------- | ------------------------------------------------------------------------------------------------------------------------- |
| Test sharding across runners     | The suite runs in parallel in a couple of minutes. Sharding buys nothing until it does not.                               |
| Lighthouse / performance budgets | Scores swing with the runner, so the gate would be flaky and get ignored. axe-core covers the part that is deterministic. |
| `composer-require-checker`       | Overlaps `composer-unused` from the other direction and is noisy against a framework that resolves a lot at runtime.      |
| envy (`.env` drift)              | One `.env.example` and `app:doctor` already catch a missing key, and the check fires on every unrelated config change.    |

The agent and documentation work left these out:

| Skipped                                   | Why                                                                                                                                                                                                     |
| ----------------------------------------- | ------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| `bin/setup`, Sail, a devcontainer         | A third way to start the app that has to be kept in step with the other two. `composer setup` handles a fresh clone and `php artisan dev` runs the daily loop; both are already tested by `app:doctor`. |
| A Scout-indexed, searchable wiki          | Forces the search-driver decision the resource spine deliberately does not make, for a corpus of a few dozen files that `rg` reads in milliseconds.                                                     |
| A language model anywhere in the pipeline | `/document` runs on a developer's machine. Putting it in CI means an API key in the build, a cost on every push, and a non-deterministic gate.                                                          |

The AI layer left these out:

| Skipped                                              | Why                                                                                                                                                      |
| ---------------------------------------------------- | -------------------------------------------------------------------------------------------------------------------------------------------------------- |
| A chat product                                       | A thread UI, history, sharing and moderation are a product, not a layer. The blocks, tools and confirm tokens are what a chat product would be built on. |
| Billing for AI spend                                 | Billing-shaped, like the rest of it. The credit ledger and `ai:usage` are the meter a billing module would read.                                         |
| Images, speech, transcription, provider file storage | The SDK supports all four. None of them has a place in this kit yet, and each drags in storage, moderation and a second provider account.                |
| Reranking                                            | Useful once retrieval is tuned against a real corpus. There is no corpus here to tune against.                                                           |
| Provider-hosted vector stores                        | pgvector in the application's own database keeps the corpus inside the tenancy boundary the rest of the kit enforces. A hosted store moves it outside.   |

The resource spine was cut back for the same reason — the pattern pays off with
several consumers reading one adapter, and this kit has none yet:

| Skipped                                                 | Why                                                                                                                                                                                 |
| ------------------------------------------------------- | ----------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| `searchQuery()`, Scout, a generic `/search`, ⌘K palette | Scout forces a driver decision (Meilisearch, Typesense, database) a starter should not make for you, and search across one model is `where name like`. `url()` keeps the seam open. |
| `visibleTo()` / `scopeFilter()` / `find()`              | These exist to make search results safe. Policies already answer that question, and duplicating the rule in an adapter is two places to get it wrong. They come back with Scout.    |
| `actions()` / `actionSchemas()`                         | Only useful to an AI assistant layer that is not here. `app/Actions` is already the seam one would build on.                                                                        |
| `ApiExposable` / `ApiWritable` REST surface             | Drags in Sanctum, an ability catalog, versioning and pagination conventions. The "no token-auth API" line above is the decision; the registry is the seam that module hangs off.    |
| Resource loom (spec generator, archetypes, codemods)    | A real package, but it assumes a tenant kit, a resource kit and a branding kit underneath. Adopting it means inheriting four packages.                                              |
| AI presentation manifest and drafter                    | Only meaningful once the loom generator is in.                                                                                                                                      |
| Cheatsheet parity CI                                    | Machinery for a package that is not here.                                                                                                                                           |
| Motion layer (`useLoomMotion`, `<CountUp>`)             | Cut deliberately. The value components render, they do not animate.                                                                                                                 |
| Guard G6 (precognition on form routes)                  | Precognition is not used in this kit yet. Add the guard alongside the feature.                                                                                                      |

`todo/specs/` holds a draft spec for a theming system — a design note, not
shipped code.
