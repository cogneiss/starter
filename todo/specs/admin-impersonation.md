# Spec: Admin impersonation

## 1. Problem

Support and debugging need a safe way for an admin to see the app as a specific user, and to get back out. The kit has no admin concept at all today, so this lands two things: a minimal admin flag and an audited impersonation flow.

## 2. Approach

- **Package: `franbarbalopez/mirror`.** PHP 8.2+ / Laravel 11–13 only, so no PHP 7 compatibility layers in code we debug. It ships the three things we would otherwise hand-roll badly: HMAC-signed session state, a TTL on the impersonation session, and arbitrary `context` metadata on the lifecycle events for audit. It refuses non-session guards and nested impersonation by design, which is the right posture for a support back door.
- **Rejected: `lab404/laravel-impersonate`.** Far more installed (19.8M vs 37k) but its advantages are the parts we would not use — the `Route::impersonate()` macro, Blade directives, a permissive guard story. It has no signature on the session state, no TTL, and no audit context, so we would be adding those on top. Its PHP `^7.2` floor also means the codebase is written to an older language level.
- **Admin flag as a column.** `users.is_admin`, boolean, default false. A config allowlist of emails was considered and dropped: the flag needs to be queryable and per-environment seedable, and a starter kit should hand people a real column rather than a config trick.
- **Inertia gets the state as a shared prop**, not Blade directives. `HandleInertiaRequests::share()` already carries `auth.user` and `sidebarOpen`; impersonation state joins it, and the UI renders a persistent banner.
- **Sensitive routes are blocked during impersonation.** Mirror has no equivalent of lab404's `impersonate.protect`, so we add a small middleware. Without it an impersonator can change the victim's password, delete their account, or enrol a passkey — which turns a support tool into privilege escalation.

## 3. Changes

**New:**

- `database/migrations/xxxx_add_is_admin_to_users_table.php` — boolean, default false.
- `app/Actions/CreateUserImpersonation.php` — wraps `Mirror::impersonate($user, context: [...])`, passing the acting admin id, an optional reason from the request, and `'source' => 'admin-ui'`.
- `app/Actions/DeleteUserImpersonation.php` — wraps `Mirror::leave()`.
- `app/Http/Controllers/UserImpersonationController.php` — `store()` and `destroy()`.
- `app/Http/Requests/CreateUserImpersonationRequest.php` — authorises via the model's `canImpersonate()` / `canBeImpersonated()` and validates the optional reason string.
- `app/Http/Middleware/DenyWhileImpersonating.php` — aborts 403 when an impersonation session is active.
- `app/Listeners/LogImpersonation.php` — listens to Mirror's `ImpersonationStarted` / `ImpersonationStopped` and writes to the log channel with the event context.
- `resources/js/components/impersonation-banner.tsx` — fixed banner naming the impersonated user with an exit action. Lives in platform components for now; if the theming spec's P1 lands first it belongs in `themes/default` chrome instead.
- Tests: `tests/Unit/Actions/CreateUserImpersonationTest.php`, `DeleteUserImpersonationTest.php`, `tests/Unit/Middleware/DenyWhileImpersonatingTest.php`, `tests/Feature/Controllers/UserImpersonationControllerTest.php`.

**Modified:**

- `composer.json` — `franbarbalopez/mirror`.
- `app/Models/User.php` — implement `Mirror\Contracts\Impersonatable`; `canImpersonate(): bool => $this->is_admin`, `canBeImpersonated(): bool => ! $this->is_admin`; add `is_admin` to the `@property-read` block and to `casts()` as `boolean`.
- `database/factories/UserFactory.php` — an `admin()` state.
- `database/seeders/DatabaseSeeder.php` — seed one admin alongside the existing test user.
- `routes/web.php` — inside the `auth` group: `POST user/{user}/impersonation` (`user-impersonation.store`) and `DELETE impersonation` (`user-impersonation.destroy`). Apply `DenyWhileImpersonating` to `user.destroy`, `password.update`, `two-factor.show`, `passkey.show`, and the impersonation start route itself.
- `app/Http/Middleware/HandleInertiaRequests.php` — share `impersonating` as `null` or `{ id, name }` of the original admin.
- `resources/js/types/index.d.ts` (or wherever `SharedData` lives) — add the prop.
- `resources/js/layouts/**` — mount the banner in the app shell.
- `tests/Unit/Middleware/HandleInertiaRequestsTest.php` — assert the new prop.

**Untouched:** Fortify, passkeys, the session/login flow.

## 4. Edge cases & risks

- **Mirror's exact API surface** — the facade methods for reading active state and the event class namespaces are taken from the README; confirm against the installed source before wiring the Inertia prop and the listener.
- **Privilege escalation** is the whole risk of this feature. `canBeImpersonated()` returning false for admins prevents admin-to-admin hops; `DenyWhileImpersonating` prevents credential changes. Both need tests, not just code.
- **Two-factor and passkey state** — the impersonated session must never be able to enrol or disable a second factor. Covered by the middleware, but the route list has to stay in sync as new settings pages land; a test that walks the settings routes and asserts the middleware is attached is cheaper than remembering.
- **Session-backed guards only** — Mirror rejects token/stateless guards. Fine today (single `web` guard); it becomes a constraint if the kit grows an API guard.
- **TTL expiry** — behaviour when the impersonation window lapses mid-request needs a decision: silently drop back to the admin (preferred) or 403. Verify what Mirror does rather than assuming.
- **Session regeneration** — starting and leaving impersonation should regenerate the session id; check whether Mirror does it or we do.
- **Single-maintainer dependency** — 163 stars, 15 commits on the 2.x branch. Small enough to vendor into `app/` if it stalls; the Action wrappers mean only two files would change.
- **Coverage gates** — `--exactly=100.0` and type-coverage 100 mean the listener, middleware, and both actions each need a test.

## 5. Test plan

1. Feature: admin starts impersonation, is authenticated as the target, and the Inertia response carries `impersonating` with the admin's name.
2. Feature: `DELETE impersonation` restores the admin session.
3. Feature: a non-admin posting to the start route gets 403.
4. Feature: an admin targeting another admin gets 403.
5. Feature: while impersonating, `PUT settings/password`, `DELETE user`, `GET settings/two-factor`, and `GET settings/passkeys` all return 403.
6. Feature: starting an impersonation while already impersonating is rejected.
7. Unit: `DenyWhileImpersonating` passes the request through when no impersonation is active.
8. Unit: the actions call Mirror with the expected user and context payload.
9. Unit: `HandleInertiaRequests` shares `null` for a normal session.
10. `composer test` green — lint, rector, type-coverage 100, phpstan, unit coverage 100.
