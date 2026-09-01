---
title: Data objects
status: current
supersedes: []
code_refs:
    - app/Data/UserData.php
    - app/Data/OrganizationData.php
    - app/Data/OrganizationMemberData.php
    - app/Data/OrganizationInvitationData.php
    - app/Data/BrowserSessionData.php
    - app/Data/LoginHistoryData.php
    - app/Data/PasskeyData.php
    - app/Data/ImpersonatorData.php
updated: 2026-09-01
---

# Data objects

Eight `spatie/laravel-data` classes in `app/Data`. Every Inertia payload is one of
these; a raw array or an Eloquent model never goes to `Inertia::render`.

| Class                        | Carries                                        |
| ---------------------------- | ---------------------------------------------- |
| `UserData`                   | the signed-in user, including `is_super_admin` |
| `OrganizationData`           | the current organization                       |
| `OrganizationMemberData`     | a member row with role and status              |
| `OrganizationInvitationData` | a pending invitation                           |
| `BrowserSessionData`         | one signed-in browser                          |
| `LoginHistoryData`           | one sign-in attempt                            |
| `PasskeyData`                | one registered WebAuthn credential             |
| `ImpersonatorData`           | who is impersonating, while impersonating      |

## Why not just pass the model

Two reasons, and the second is the one that keeps paying:

1. A model serialized to the frontend leaks whatever was added to it last.
   A Data class lists its fields.
2. Every class carries `#[TypeScript]`, so `resources/js/types/generated.d.ts`
   has an interface for it and the page importing that interface fails `tsc` when
   a field is renamed or removed. Guard G4 fails the build for a Data class
   without the attribute, because without it the class silently never reaches the
   generated types ([[architecture/convention-guards]]).

After touching anything here:

```bash
composer typescript:generate
```

and commit the generated file. `php artisan app:doctor` reports it as stale
otherwise. The full chain is in [[architecture/type-safety]].
