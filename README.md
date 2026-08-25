> **Cogneiss Starter Kit.** Originally based on
> [nunomaduro/laravel-starter-kit-inertia-react](https://github.com/nunomaduro/laravel-starter-kit-inertia-react),
> now maintained independently. It adds passkey (WebAuthn) sign-in, magic-link
> login, organizations with role-based access control, a resource spine with an
> `app:make-resource` scaffolder, and a
> [Base UI](https://base-ui.com) component layer (shadcn `base-nova`) in place of Radix.
> See [FEATURES.md](FEATURES.md) for the full list.

<p>
    <a href="https://github.com/cogneiss/starter/actions"><img src="https://github.com/cogneiss/starter/actions/workflows/tests.yml/badge.svg" alt="Build Status"></a>
    <a href="https://github.com/cogneiss/starter/releases"><img src="https://img.shields.io/github/v/release/cogneiss/starter?sort=semver" alt="Latest Release"></a>
    <a href="https://opensource.org/licenses/MIT"><img src="https://img.shields.io/badge/license-MIT-blue" alt="License"></a>
</p>

**Laravel Starter Kit (Inertia & React)** is an ultra-strict, type-safe [Laravel](https://laravel.com) skeleton engineered for developers who refuse to compromise on code quality. This opinionated starter kit enforces rigorous development standards through meticulous tooling configuration and architectural decisions that prioritize type safety, immutability, and fail-fast principles.

## Why This Starter Kit?

Modern PHP has evolved into a mature, type-safe language, yet many Laravel projects still operate with loose conventions and optional typing. This starter kit changes that paradigm by enforcing:

- **Fully Actions-Oriented Architecture**: Every operation is encapsulated in a single-action class
- **Cruddy by Design**: Standardized CRUD operations for all controllers, actions, and Inertia & React pages
- **100% Type Coverage**: Every method, property, and parameter is explicitly typed
- **Typed Payloads, Not Just Typed Routes**: Wayfinder types your routes; `laravel-data` plus `#[TypeScript]` types every Inertia payload, so a renamed field breaks `tsc` instead of rendering `undefined`
- **One Command Per New Model**: `php artisan app:make-resource Project` writes the model, migration, Data class, policy, action, request, controller, adapter, page and tests — all of it passing `composer test` unedited
- **Zero Tolerance for Code Smells**: Rector, PHPStan, OxLint, and Oxfmt at maximum strictness catch issues before they become bugs
- **Immutable-First Architecture**: Data structures favor immutability to prevent unexpected mutations
- **Fail-Fast Philosophy**: Errors are caught at compile-time, not runtime
- **Automated Code Quality**: Pre-configured tools ensure consistent, pristine code across your entire team
- **Just Better Laravel Defaults**: Thanks to **[Essentials](https://github.com/nunomaduro/essentials)** / strict models, auto eager loading, immutable dates, and more...
- **AI Guidelines**: Integrated AI Guidelines to assist in maintaining code quality and consistency
- **Code Knowledge Graphs**: Four indexes of the codebase, rebuilt after every commit, so AI agents answer "what calls this" and "what breaks if I change it" from an index instead of burning tokens crawling files
- **An AI layer, not a chatbot**: `laravel/ai` wired into the same architecture as everything else — agents scoped to one organization, read-only tools that call `authorizeFor()`, writes that go through a single-use confirm token, untrusted text fenced before it reaches a model, and every run metered against a per-org budget. It boots and passes the whole suite with zero keys configured, and no test may reach a provider ([`app/Ai/`](app/Ai), [wiki](wiki/domains/ai-layer-overview.md))
- **Documentation That Fails The Build**: A linted [wiki](wiki/index.md) whose pages name the files they describe, three loadable skill packs, and one generated source for every agent guideline file — change a documented file without updating its page and CI goes red
- **Organizations Built In**: Fail-closed tenant scoping, membership lifecycle, and role-based access control from the first commit, instead of a security migration later
- **Two-Gate Authorization**: Every policy check pairs an ownership policy with a named permission, enforced by a test rather than by discipline
- **Full Testing Suite**: 493 tests with 100% code coverage using Pest
- **Quality Gates That Block**: Every pull request runs the suite, secret and dependency scanning, dead-code and unused-dependency detection, and axe-core over every page; the SQLite and mutation runs happen on a schedule

This isn't just another Laravel boilerplate—it's a statement that PHP applications can and should be built with the same rigor as strongly-typed languages like Rust or TypeScript.

## Getting Started

> **Requires [PHP 8.5+](https://php.net/releases/) and a code coverage driver like [xdebug](https://xdebug.org/docs/install)**.

This kit is not published to Packagist. Clone it:

```bash
git clone https://github.com/cogneiss/starter.git example-app
```

### Initial Setup

Navigate to your project and complete the setup:

```bash
cd example-app

# Setup the project (installs, keys, migrates, seeds the role templates, builds)
composer setup

# Optional: seed a user you can sign in with (test@example.com / password)
php artisan db:seed

# Start the development server
composer dev
```

While you are working, `composer test:fast` is the quick loop — parallel,
compact, stops at the first failure. `composer test` is the gate that has to
pass before you push; it is the same one CI runs.

If anything misbehaves, run `php artisan app:doctor` — it checks PHP, extensions,
the coverage driver, `.env`, the database, migrations, bun, the Vite manifest and
the generated TypeScript, and prints the command that fixes whatever failed.

### Environment

`composer setup` copies `.env.example` and everything works out of the box. The
knobs worth knowing:

| Variable                          | Default   | What it does                                                                                                             |
| --------------------------------- | --------- | ------------------------------------------------------------------------------------------------------------------------ |
| `ORGANIZATIONS_STRICT`            | `true`    | Query a scoped model with no organization bound and it throws. Set to `false` only while migrating an existing database. |
| `ORGANIZATIONS_RESOLVER`          | `session` | How the current organization is found: `session`, `subdomain`, or `single`.                                              |
| `GOOGLE_CLIENT_ID` / `_SECRET`    | empty     | Google social login.                                                                                                     |
| `GITHUB_CLIENT_ID` / `_SECRET`    | empty     | GitHub social login.                                                                                                     |
| `MICROSOFT_CLIENT_ID` / `_SECRET` | empty     | Microsoft social login.                                                                                                  |
| `ANTHROPIC_API_KEY`               | empty     | The AI layer's default provider. Blank is a supported state: agents simply have nothing to call.                         |
| `AI_FAKE`                         | `false`   | Answer every agent from a canned response instead of a provider.                                                         |

Social login stays off until you fill in a provider's keys and enable the
feature flag — see [SETUP.md](SETUP.md). The same is true of the AI layer:
with no key set the app boots, every page renders and `composer test` passes.

### Optional: Browser Testing Setup

If you plan to use Pest's browser testing capabilities:

```bash
bun add playwright
bunx playwright install
```

### Verify Installation

Run the test suite to ensure everything is configured correctly:

```bash
composer test
```

You should see 100% test coverage and all quality checks passing.

## Available Tooling

### Development

- `composer dev` - Starts Laravel server, queue worker, log monitoring, and Vite+ dev server concurrently

### Frontend

- `bun run dev` - Vite+ dev server on its own (already included in `composer dev`)
- `bun run build` - Production build
- `bun run build:ssr` - Production build plus the SSR bundle

### Code Quality

- `composer lint` - Runs Rector (refactoring), Pint (PHP formatting), and Oxfmt (JS/TS formatting)
- `composer test:lint` - Dry-run mode for CI/CD pipelines

### Testing

- `composer test:type-coverage` - Ensures 100% type coverage with Pest
- `composer test:types` - Runs PHPStan (Larastan) at level max, plus `tsc --noEmit`
- `composer test:unit` - Runs Pest tests with 100% code coverage requirement
- `composer test` - Runs the complete test suite (type coverage, unit tests, linting, static analysis)
- `composer test:fast` / `composer test:dirty` - Quick local loops; see [SETUP.md](SETUP.md)
- `composer test:a11y` - axe-core over every page the kit ships
- `composer test:audit`, `composer test:dead-code`, `composer test:deps`, `composer test:knip` - The other blocking CI gates
- `composer test:sqlite`, `composer test:mutate`, `composer test:evals` - The scheduled runs, on demand

### Code knowledge graphs

- `php artisan brain:scan` - Rescans the Laravel graph (routes, models, events, jobs). Runs automatically after every commit
- `php artisan brain:export-context --route=/settings/profile` - Budgeted AI context for one request path
- Three more graph tools (graphify, code-review-graph, gitnexus) are optional — see [SETUP.md](SETUP.md)

### Maintenance

- `composer update:requirements` - Updates all PHP and Bun dependencies to latest versions

### Staying in sync with upstream

```bash
git remote add upstream https://github.com/nunomaduro/laravel-starter-kit-inertia-react.git
git fetch upstream && git merge upstream/main
```

## License

**Laravel Starter Kit Inertia React** was created by **[Nuno Maduro](https://x.com/enunomaduro)** under the **[MIT license](https://opensource.org/licenses/MIT)**. This fork is maintained by Cogneiss under the same license.
