---
title: Convention guards G1, G4 and G5
status: current
supersedes: []
code_refs:
    - tests/Unit/Conventions/ConventionTest.php
    - config/conventions.php
updated: 2026-08-25
---

# Convention guards G1, G4 and G5

Three architecture tests in `tests/Unit/Conventions/ConventionTest.php` fail the
build when a new file skips a convention.

| Guard | Requires                                                                                              |
| ----- | ----------------------------------------------------------------------------------------------------- |
| G1    | every model in `app/Models` has a factory                                                             |
| G4    | every class in `app/Data` carries `#[TypeScript]`, and so does every `dataClass()` an adapter returns |
| G5    | every model has a resource adapter                                                                    |

Each guard exists because its failure is silent otherwise. A model with no
factory means the next test that needs one hand-builds a row and drifts from the
schema. A Data class with no `#[TypeScript]` never reaches the generated types, so
the page importing it types the payload as whatever the author assumed. A model
with no adapter cannot produce a URL, which is where the frontend `switch` comes
back.

## Exceptions carry a reason, not a checkbox

`config/conventions.php` is keyed by class with a reason string as the value:

```php
'models_without_factory' => [
    Role::class => 'Spatie permission role, written by RoleTemplateSeeder rather than by a factory.',
],
```

The shape is the point. A flat list of exempt class names tells a reviewer
nothing, so a stale entry survives forever. A reason string is readable in the
diff, and an entry whose reason no longer holds is obvious.

`non_resource_models` currently exempts `FeatureOverride` and `Role` as _pending
resource adapter_, and `ImpersonationLog`, `LoginHistory`, `RoleTemplate` and
`SocialAccount` as models a user never navigates to. Two of those reasons are
temporary and say so.

The AI layer added four more, and each reason names what reads the table instead
of a resource page: `AiAuditLog` is reported in aggregate by `php artisan
ai:usage`, `AiCreditLedgerEntry` is summed for a balance,
`AiDocument` is reached only through a retrieval tool
([[domains/ai-retrieval]]), and `AiMemory` is read into one person's own prompt
([[domains/ai-memory]]). None of the four has a URL, which is exactly the claim
G5 makes you write down. Giving one an adapter later means deleting its line
here, and the deletion is the review.

## The rule around them

Add the exception; never weaken the guard. Each failure message names the exact
command that fixes it — usually `php artisan app:make-resource <Name>` for G5.

G6 (precognition on form routes) was considered and left out because precognition
is not used in this kit yet; see [[decisions/not-included]].
