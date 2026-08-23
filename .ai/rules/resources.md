---
paths:
  - 'app/Models/**, app/Data/**, app/Resources/**, app/Http/Controllers/**'
---

# Resources

## Resource spine, typed payloads and the generator
- New user-facing model? Run `php artisan app:make-resource <Name>`. Do not hand-write the eight files: guard G5 fails CI, and the guards expect exactly what the generator emits.
- Every Inertia payload is a `#[TypeScript]` Data class in `app/Data`. Never pass raw arrays or models to `Inertia::render`.
- After touching anything in `app/Data`, run `composer typescript:generate` and commit `resources/js/types/generated.d.ts`.
- Never weaken a convention guard. Add the genuine exception to `config/conventions.php` with a reason string.
- `ResourceContract` has six methods on purpose. Adding a seventh means a new consumer exists — say what it is in the PR.
- Build `url()` from Wayfinder helpers, never a string literal.
