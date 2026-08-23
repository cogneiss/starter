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

- Pest 5 with 473 tests covering every controller, action, rule, and middleware.
- Coverage gate at `--exactly=100.0` — not a minimum, an exact match, so dead
  code fails the build too.
- Architecture presets (php, strict, laravel, security) catch `dd()` leftovers,
  loose comparisons, non-final classes, and insecure function calls.
- Browser tests through `pest-plugin-browser` and Playwright, with screenshots
  uploaded on CI failure.
- Tests run in parallel, with frozen time, faked sleep, and stray HTTP and
  process calls blocked by default.
- Test Impact Analysis enabled locally — only the tests your change touches run.
- `UserFactory` ships `unverified()` and `withoutTwoFactor()` states, and
  `db:seed` creates `test@example.com` / `password` for local sign-in.

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

| Command                                    | What it does                                                                  |
| ------------------------------------------ | ----------------------------------------------------------------------------- |
| `composer setup`                           | Install, key, migrate, build. One shot from a fresh clone.                    |
| `composer dev`                             | Server, queue worker, log tail, and Vite together.                            |
| `composer test`                            | Lint, type coverage, PHPStan, and the test suite — the same gate CI runs.     |
| `composer lint`                            | Rector, Pint, and the frontend formatter, applying fixes.                     |
| `composer update:requirements`             | Bump PHP and JS dependencies to latest.                                       |
| `composer typescript:generate`             | Rewrite `resources/js/types/generated.d.ts` from the `#[TypeScript]` classes. |
| `php artisan app:make-resource <Name>`     | Scaffold a model and everything around it, tests included.                    |
| `php artisan app:doctor`                   | Check that this machine can run, test and build the app.                      |
| `php artisan resource:cache`               | Cache the resource registry for production (`resource:clear` undoes it).      |
| `php artisan app:sync-permissions`         | Write the permission catalog to the database.                                 |
| `php artisan app:expire-feature-overrides` | Drop feature overrides whose expiry has passed.                               |

CI runs `composer test` on every push and pull request against `main`, with
Composer, Bun, Playwright, Rector, and PHPStan caches warm.

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
