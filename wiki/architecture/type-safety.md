---
title: Type safety, end to end
status: current
supersedes: []
code_refs:
    - app/Data/UserData.php
    - app/Providers/TypeScriptTransformerServiceProvider.php
    - config/data.php
    - phpstan.neon
    - tests/Unit/TypeScriptGenerationTest.php
updated: 2026-08-24
---

# Type safety, end to end

The chain runs from the database to the React page, and every link is checked by
a gate rather than by review.

## PHP side

- PHPStan with Larastan at level max, configured in `phpstan.neon` across `app`,
  `config`, `database`, `routes`, `public` and `bootstrap`.
- 100% type coverage (`composer test:type-coverage`, `--min=100`): every
  parameter, property and return annotated, or the build fails.
- `declare(strict_types=1)` and `final` classes, enforced by the Pest strict
  architecture preset in `tests/Unit/ArchTest.php`.

## Crossing the boundary

Two separate mechanisms, and it is worth keeping them straight:

- **Wayfinder** generates typed TS functions from routes and controllers, so a
  renamed route breaks the frontend build rather than production. Imports come
  from `@/actions/` (controllers) and `@/routes/` (named routes).
- **Typed payloads** — every Inertia payload is a `spatie/laravel-data` class in
  `app/Data` carrying `#[TypeScript]`, and
  `spatie/laravel-typescript-transformer` (configured in
  `app/Providers/TypeScriptTransformerServiceProvider.php`) turns those into TS
  interfaces. Pages import the generated type, so a renamed or removed field
  breaks `tsc` instead of rendering `undefined`.

Routes typed without payloads typed still lets a field rename through silently,
which is why both exist.

## The generated file is committed on purpose

`resources/js/types/generated.d.ts` is in the repository. CI, an editor and a
fresh clone all see the types with no build step, and a stale file shows up as a
diff in review. `php artisan app:doctor` checks for exactly that staleness, and
`tests/Unit/TypeScriptGenerationTest.php` covers the generation itself.

After touching anything in `app/Data`, run:

```bash
composer typescript:generate
```

The Data classes themselves are listed in [[domains/data-objects]]. The guard
that stops a Data class shipping without `#[TypeScript]` is G4, in
[[architecture/convention-guards]].
