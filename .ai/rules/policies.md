---
paths:
  - 'app/Policies/**'
---

# Policies

## Two gates on every authorization check: policy and permission
Every policy method checks both an ownership/relationship condition and a named permission (`$user->can('members.invite')`). A convention test enforces it, so a policy that only checks one gate fails the suite.

Permission names are `<resource>.<verb>`, lowercase and dot-separated, and every one is declared in `App\Support\PermissionCatalog`. An unregistered or misnamed permission fails the guard test rather than silently denying access; `php artisan app:sync-permissions` writes the catalog to the database.

Every feature flag goes in `App\Enums\KnownFeatures` before it is used anywhere.
