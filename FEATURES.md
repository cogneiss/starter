# Features

What ships in this starter kit, split into what your users touch and what you
touch. Setup instructions live in [README.md](README.md).

## User-facing

### Authentication

| Feature | Details |
| --- | --- |
| Registration | Name, email, password. Sends a verification email and logs the user in. |
| Password login | Email and password, with "remember me" and rate limiting. |
| Magic link | Request a one-time login link by email. Tokens last 15 minutes, work once, and never reveal whether an address is registered. |
| Passkeys | Register and sign in with WebAuthn (Touch ID, Face ID, Windows Hello, hardware keys) via Fortify. Passwordless sign-in from the login page. |
| Two-factor auth | TOTP with a QR code, confirmation step, and single-use recovery codes. Enabling it requires a password confirmation. |
| Password reset | Emailed reset link with the usual token expiry and throttling. |
| Email verification | Signed verification links, resend endpoint, and a `verified` guard on the dashboard. |
| Password confirmation | Re-prompts for the password before sensitive settings changes. |

Every path respects two-factor: magic links and passkeys hand a 2FA user to the
challenge screen instead of logging them straight in.

### Account settings

- **Profile** — update name and email; changing the email drops verification and
  requires re-verifying.
- **Password** — update with current-password confirmation, throttled.
- **Passkeys** — list, name, and delete registered credentials.
- **Two-factor** — enable, confirm, view and regenerate recovery codes, disable.
- **Appearance** — light, dark, or follow the system, persisted in a cookie so
  the server renders the right theme on first paint (no flash).
- **Delete account** — password-confirmed, permanent.

### Pages

A public welcome page at `/`, a dashboard behind auth and email verification,
and the auth and settings screens listed above. Everything else is yours to
build.

### Interface

- Inertia v3 + React 19 single-page experience with server-side routing.
- Two app shells (sidebar or header nav) and three auth layouts (simple, card,
  split) — swap by changing one import.
- shadcn-style component set on Radix primitives, so keyboard navigation, focus
  management, and ARIA come for free.
- Tailwind v4, responsive down to mobile, collapsible sidebar with persisted
  state.
- Password fields have a show/hide toggle with a proper `aria-label`.
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
- **Form requests** for every write, with a reusable `ValidEmail` rule.
- **UUID primary keys** on users, hidden sensitive attributes, and typed
  `@property-read` docblocks on the model.
- `declare(strict_types=1)` and `final` classes everywhere, enforced by Pest's
  strict architecture preset.

### Type safety

- PHPStan (Larastan) at **level max** across `app`, `config`, `database`,
  `routes`, `public`, and `bootstrap`.
- **100% type coverage** gate — every parameter, property, and return type is
  annotated or the build fails.
- TypeScript checked with `tsc --noEmit`, plus type-aware linting in Vite+.
- **Wayfinder** generates typed TS functions from PHP routes and controllers, so
  a renamed route breaks the frontend build instead of production.

### Testing

- Pest 5 with 180+ tests covering every controller, action, rule, and middleware.
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
  `_boost/browser-logs`) plus per-domain skills in `.claude/skills/` and
  guidelines in `.ai/`, so AI agents get version-correct docs and this project's
  conventions.
- `AGENTS.md`, `CLAUDE.md`, `GEMINI.md`, and `opencode.json` ship configured.
- **Pail** for readable log tailing during development.
- `php artisan make:action` scaffolds an action; Essentials also ships its own
  Rector and Pint commands.

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

| Command | What it does |
| --- | --- |
| `composer setup` | Install, key, migrate, build. One shot from a fresh clone. |
| `composer dev` | Server, queue worker, log tail, and Vite together. |
| `composer test` | Lint, type coverage, PHPStan, and the test suite — the same gate CI runs. |
| `composer lint` | Rector, Pint, and the frontend formatter, applying fixes. |
| `composer update:requirements` | Bump PHP and JS dependencies to latest. |

CI runs `composer test` on every push and pull request against `main`, with
Composer, Bun, Playwright, Rector, and PHPStan caches warm.

## Not included

Deliberately absent, so you add what your product actually needs: billing,
teams and roles, an admin panel, a REST or GraphQL API with token auth,
localization, and any file upload UI (Laravel's local-disk `storage.local`
routes exist, nothing is built on them). The kit stops at "a user can sign in
safely and manage their account".
