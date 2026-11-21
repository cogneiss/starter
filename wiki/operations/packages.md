---
title: Packages
status: current
supersedes: []
code_refs:
    - tests/Feature/Docs/PackageInventoryTest.php
    - tests/Feature/ZeroKeyBootTest.php
    - app/Providers/AppServiceProvider.php
    - config/permission.php
    - config/activitylog.php
    - config/pennant.php
    - config/fortify.php
    - config/boost.php
    - config/ai.php
    - config/inertia.php
    - vet.json
    - bin/tamper-guard
updated: 2026-11-21
---

# Packages

Every package in `composer.json` and `package.json` has one row here. The row
says what the package does in this app, where its settings live and which page
explains it. `tests/Feature/Docs/PackageInventoryTest.php` fails when a package
is installed without a row, or when a row names a package that is gone.

## Zero setup

A fresh clone with an empty `.env` runs every package with no publish or
install step. Two rules keep it that way.

A package config file in `config/` lists only the values that differ from the
package's own defaults. `config/permission.php` turns on teams and swaps the
role model. `config/activitylog.php` swaps the activity model.
`config/pennant.php` changes the default store. `config/inertia.php` publishes
only the `ssr` key, so it lists every ssr option the vendor file does and turns
server rendering on with `INERTIA_SSR_ENABLED`. `config/ai.php` sets the
default provider and embedding caching and adds the app's tiers, quotas and
guardrails, while the provider list itself comes from laravel/ai. Each file
says so in its docblock. A package with nothing to change has no file at all, so
`config/reverb.php`, `config/data.php`, `config/sentry.php`
and their kind are not here. The package merges its defaults underneath the
app's file one level deep, so a key that is overridden replaces the whole
default array of that name.

A package that needs a provider key binds a null implementation when the key
is blank, as Sentry does in `app/Providers/AppServiceProvider.php`
([[operations/ops-error-reporting]]). `tests/Feature/ZeroKeyBootTest.php`
boots the app with the keys empty and fails a change that makes one required.

## PHP runtime

| Package | What it does here | Configured in | Read more |
| --- | --- | --- | --- |
| `laravel/framework` | The framework. | `config/app.php` and the other framework files | [[operations/runtime]] |
| `inertiajs/inertia-laravel` | Server half of the Inertia React app. | `config/inertia.php` | [[features/interface]] |
| `laravel/ai` | Agents, tools, embeddings and the MCP server. | `config/ai.php` | [[domains/ai-layer-overview]] |
| `laravel/fortify` | Login, two-factor and passkeys. The kit owns registration, password reset and profile routes. | `config/fortify.php` | [[features/authentication]] |
| `laravel/mcp` | The MCP server in `app/Mcp` builds on it. Required directly, since otherwise only the dev-only `laravel/boost` would pull it in. | package defaults | [[domains/ai-layer-overview]] |
| `laravel/pennant` | Feature flags, resolved from the `feature_overrides` table. | `config/pennant.php` | [[domains/feature-flags]] |
| `laravel/reverb` | WebSocket server for organization channels. | package defaults | [[domains/ux-realtime-notifications]] |
| `laravel/sanctum` | API tokens scoped to one organization. | package defaults | [[operations/ops-api-tokens]] |
| `laravel/socialite` | Google and GitHub sign-in. A button shows only when its keys are set. | `config/services.php` | [[features/authentication]] |
| `laravel/wayfinder` | Typed TypeScript functions for every route and controller action. | `vite.config.ts` | [[architecture/type-safety]] |
| `sentry/sentry-laravel` | Error reporting when `SENTRY_DSN` is set. | `config/services.php` | [[operations/ops-error-reporting]] |
| `nunomaduro/essentials` | Stricter framework defaults such as strict models, immutable dates and a forced URL scheme. The app's file only adds `Unguard`. | `config/essentials.php` | [[architecture/better-defaults]] |
| `spatie/laravel-activitylog` | Storage for the audit log. | `config/activitylog.php`, `config/audit.php` | [[operations/ops-audit-log]] |
| `spatie/laravel-data` | Typed data objects for requests and Inertia props. | package defaults | [[domains/data-objects]] |
| `spatie/laravel-health` | The check registry behind `GET /health`. Cache uses the package's own check; the database, queue, schedule, disk and debug-mode checks are kit classes that extend its `Check`. Results stay in memory. | `config/health.php` | [[operations/ops-health]] |
| `spatie/laravel-permission` | Roles and permissions, one set per organization (teams mode). | `config/permission.php` | [[domains/authorization]] |
| `spatie/laravel-typescript-transformer` | Writes `resources/js/types/generated.d.ts` from PHP data objects and enums. | `app/Providers/TypeScriptTransformerServiceProvider.php` | [[architecture/type-safety]] |

## PHP development

| Package | What it does here | Configured in | Read more |
| --- | --- | --- | --- |
| `pestphp/pest` | Test runner. | `phpunit.xml`, `tests/Pest.php` | [[operations/testing]] |
| `pestphp/pest-plugin-laravel` | Laravel helpers for Pest. | package defaults | [[operations/testing]] |
| `pestphp/pest-plugin-browser` | Browser tests in `tests/Browser`, driven by Playwright. | package defaults | [[operations/testing]] |
| `pestphp/pest-plugin-type-coverage` | `composer test:type-coverage`, held at 100%. | `composer.json` | [[operations/testing]] |
| `pestphp/pest-plugin-evals` | Scores agent answers in `tests/Evals` with text, tool-call and LLM-judge expectations. Runs only with `--evals`, so `composer test` never reaches a provider. | `tests/Pest.php` | [[domains/ai-evals]] |
| `fakerphp/faker` | Fake values for model factories. | package defaults | [[operations/testing]] |
| `mockery/mockery` | Test doubles. | package defaults | [[operations/testing]] |
| `nunomaduro/collision` | Readable errors in the console and test output. | package defaults | [[operations/testing]] |
| `laravel/pao` | Switches Pest, PHPStan, Pint and Rector output to compact JSON when an AI agent runs them. Nothing to call. | package defaults | [[operations/testing]] |
| `larastan/larastan` | PHPStan at level max with Laravel awareness. | `phpstan.neon` | [[architecture/type-safety]] |
| `phpstan/phpstan-strict-rules` | Extra strictness in `composer test:strict`. | `phpstan-strict.neon` | [[operations/commands]] |
| `phpstan/phpstan-deprecation-rules` | Flags calls to deprecated APIs in `composer test:strict`. | `phpstan-strict.neon` | [[operations/commands]] |
| `shipmonk/phpstan-rules` | More strict rules in `composer test:strict`. | `phpstan-strict.neon` | [[operations/commands]] |
| `tomasvotruba/cognitive-complexity` | Caps method and class complexity in `composer test:strict`. | `phpstan-strict.neon` | [[operations/commands]] |
| `shipmonk/dead-code-detector` | `composer test:dead-code`. | `phpstan-deadcode.neon` | [[operations/tooling]] |
| `nikic/php-parser` | Reads PHP as a syntax tree for `bin/tamper-guard`, which refuses a commit that adds a skipped test, and for `tests/Unit/Conventions/RawSqlConventionTest.php`. Required directly, since otherwise only dev tools would pull it in. | none | [[operations/contributing]] |
| `rector/rector` | Automated refactors, run by `composer lint` and the pre-commit hook. | `rector.php` | [[operations/tooling]] |
| `driftingly/rector-laravel` | Laravel rule sets for Rector. | `rector.php` | [[operations/tooling]] |
| `laravel/pint` | Code style. | `pint.json` | [[operations/tooling]] |
| `icanhazstring/composer-unused` | `composer test:deps` fails on an unused Composer package. | `composer-unused.php` | [[operations/tooling]] |
| `heyosseus/sloppy` | Reports agent-style code debt on push. It never blocks. | package defaults | [[operations/contributing]] |
| `laravel/boost` | MCP server, skill packs and the generated agent guideline files. `config/boost.php` points Claude Code at `AGENTS.md`, so `CLAUDE.md` stays a one-line import. | `config/boost.php`, `boost.json` | [[operations/tooling]] |
| `laramint/laravel-brain` | The code knowledge graph, rebuilt after each commit. | package defaults | [[operations/code-knowledge-graphs]] |
| `laravel/pail` | Tails the log in `composer dev`. | package defaults | [[operations/tooling]] |
| `laravel/tinker` | `php artisan tinker`. | package defaults | [[operations/commands]] |
| `laravel/vet` | Holds back Composer releases younger than a day and audits on install. | `vet.json` | [[operations/tooling]] |
| `roave/security-advisories` | Blocks installing any version with a known advisory. Nothing to call. | none | [[operations/tooling]] |
| `cyclonedx/cyclonedx-php-composer` | `composer sbom`, attached to each release. | `composer.json` | [[operations/releases]] |

## JavaScript runtime

| Package | What it does here | Configured in | Read more |
| --- | --- | --- | --- |
| `react` | UI library. | package defaults | [[features/interface]] |
| `react-dom` | Renders React in the browser and for SSR. | package defaults | [[features/interface]] |
| `@inertiajs/react` | Client half of Inertia. Its `useForm` does live validation against the form request, so no Precognition package is installed. | `resources/js/app.tsx` | [[features/interface]], [[domains/ux-forms-precognition]] |
| `@inertiajs/vite` | Resolves pages and turns `app.tsx` into the SSR entry, so there is no separate `ssr.tsx`. | `vite.config.ts` | [[features/interface]] |
| `laravel-vite-plugin` | Vite entry points, the hot file and the Bunny font. | `vite.config.ts` | [[operations/tooling]] |
| `@vitejs/plugin-react` | React support for Vite, and the React Compiler preset. | `vite.config.ts` | [[operations/tooling]] |
| `babel-plugin-react-compiler` | The React Compiler, loaded by that preset. | `vite.config.ts` | [[operations/tooling]] |
| `tailwindcss` | Styling. | `resources/css/app.css` | [[domains/ux-branding]] |
| `@tailwindcss/vite` | Tailwind's Vite plugin. | `vite.config.ts` | [[domains/ux-branding]] |
| `@base-ui/react` | Headless primitives under the shadcn components. | package defaults | [[domains/ux-primitives]] |
| `class-variance-authority` | Variant props on UI components. | package defaults | [[domains/ux-primitives]] |
| `cn` | Joins class names and resolves conflicting Tailwind classes. `cn()` is re-exported from it. | `resources/js/lib/utils.ts` | [[domains/ux-primitives]] |
| `lucide-react` | Icons. | package defaults | [[domains/ux-primitives]] |
| `@tanstack/react-table` | Table rendering for the list kit. Search, sort and paging stay on the server. | `resources/js/components/data-table.tsx` | [[domains/ux-list-kit]] |
| `@laravel/echo-react` | Configures the shared Echo instance that subscribes to organization channels. | `resources/js/hooks/use-organization-channel.ts` | [[domains/ux-realtime-notifications]] |
| `laravel-echo` | Supplies the `Echo` type. `@laravel/echo-react` ships type declarations that import it without installing it, so the hook's connection is untyped when it is missing. | `resources/js/hooks/use-organization-channel.ts` | [[domains/ux-realtime-notifications]] |
| `pusher-js` | The WebSocket client `@laravel/echo-react` imports to talk to Reverb. | package defaults | [[domains/ux-realtime-notifications]] |
| `@laravel/passkeys` | Passkey registration and sign-in in the browser. | package defaults | [[features/authentication]] |
| `input-otp` | The six-digit two-factor code field. | package defaults | [[features/authentication]] |
| `recharts` | Dashboard charts. | `resources/js/components/ui/chart.tsx` | [[features/pages]] |
| `sonner` | Toasts. Its injected style is stripped so the CSP holds. | `vite.config.ts` | [[operations/ops-csp]] |
| `concurrently` | Runs PHPStan and `tsc` side by side in `composer test:types`. | `composer.json` | [[operations/commands]] |
| `typescript` | Type checking. | `tsconfig.json` | [[architecture/type-safety]] |
| `@types/react` | React types. | package defaults | [[architecture/type-safety]] |
| `@types/react-dom` | React DOM types. | package defaults | [[architecture/type-safety]] |

## JavaScript development

| Package | What it does here | Configured in | Read more |
| --- | --- | --- | --- |
| `vite` | Aliased to Vite+'s core, so plugins that import `vite` get the same build. | `package.json` | [[operations/tooling]] |
| `vite-plus` | Build, dev server, oxlint and oxfmt. | `vite.config.ts` | [[operations/tooling]] |
| `@rolldown/plugin-babel` | Runs the React Compiler inside the Rolldown build. | `vite.config.ts` | [[operations/tooling]] |
| `@laravel/vite-plugin-wayfinder` | Regenerates the Wayfinder route functions while Vite runs. | `vite.config.ts` | [[architecture/type-safety]] |
| `eslint-plugin-better-tailwindcss` | Lint rules for unknown, conflicting and deprecated Tailwind classes. | `vite.config.ts` | [[operations/tooling]] |
| `@tailwindcss/typography` | `prose` styles for Markdown content. | `resources/css/app.css` | [[features/pages]] |
| `tw-animate-css` | Enter and exit animations for the UI components. | `resources/css/app.css` | [[domains/ux-motion-and-a11y]] |
| `shadcn` | CLI that adds UI components to `resources/js/components/ui`. | `components.json` | [[features/interface]] |
| `@types/node` | Node types for config files. | package defaults | [[architecture/type-safety]] |
| `knip` | `composer test:knip` fails on unused files, exports and packages. | `knip.json` | [[operations/tooling]] |
| `npm-check-updates` | Bumps `package.json` ranges in `composer update:requirements`. | `composer.json` | [[operations/commands]] |
| `playwright` | Browser engine for the Pest browser tests. `composer setup` installs Chromium. | `composer.json` | [[operations/testing]] |
