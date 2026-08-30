---
title: Onboarding and the activation checklist
status: current
supersedes: []
code_refs:
    - app/Onboarding/StepContract.php
    - app/Onboarding/StepRegistry.php
    - app/Onboarding/Checklist.php
    - app/Onboarding/Steps/BrandOrganizationStep.php
    - app/Onboarding/Steps/InviteTeammateStep.php
    - app/Onboarding/Steps/EnableTwoFactorStep.php
    - app/Http/Middleware/RedirectIfNotOnboarded.php
    - app/Http/Controllers/OnboardingController.php
    - app/Models/OnboardingProgress.php
    - app/Console/Commands/MakeOnboardingStepCommand.php
    - resources/js/components/activation-checklist.tsx
    - database/migrations/2026_08_30_090000_create_onboarding_progress_table.php
    - tests/Feature/OnboardingProgressTest.php
    - tests/Feature/OnboardingGateResumeTest.php
    - tests/Feature/OnboardingGateSkipTest.php
    - tests/Feature/OnboardingGateNoLoopTest.php
    - tests/Feature/OnboardingWidgetPropsAbsentTest.php
    - tests/Mutations/phase12-widget-ability.patch
updated: 2026-08-31
---

# Onboarding and the activation checklist

A new organization is walked through the few things that have to be true before
the product does anything for it, and then the walkthrough gets out of the way
permanently.

## A step answers for itself

`App\Onboarding\StepContract` asks a step for its key, its title, the route that
finishes it, whether it gates entry — and, crucially, whether it is **done**. A
step answers that from the application's own data: the organization has brand
colours, an invitation exists, two-factor is on. There is no "mark complete"
call, so a step finished through its ordinary route counts immediately and
cannot be forgotten by a code path that did not know the checklist existed.

`StepRegistry` discovers every class in `app/Onboarding/Steps` implementing the
contract, so adding one is adding a file:

```bash
php artisan app:make-onboarding-step ConnectBilling
```

`required()` narrows the set to the steps that gate.

## Derived on read

`Checklist` computes state every time it is read. The only thing stored in
`onboarding_progress` is the human's decision to skip or dismiss —
`tests/Feature/OnboardingProgressTest.php` asserts a read writes nothing. Stored
completion is a second source of truth that drifts the first time a step is
undone.

## The gate

`RedirectIfNotOnboarded` holds a new organization on the onboarding screen until
the required steps are behind it, and resumes at the first one still
outstanding. Two exclusions keep it honest: the onboarding routes themselves
(otherwise the redirect is a loop) and logout (otherwise a gated person is
trapped in the application). Both have their own test.

Skipping is permanent — once skipped, the gate is gone for good rather than
reappearing next session.

## The dashboard checklist

`<ActivationChecklist>` shows what is left after the gate is passed or skipped.
The dashboard widgets alongside it are filtered by ability **in the controller**,
so a widget a person may not see is absent from the props rather than hidden in
the browser:

```bash
bin/prove-control.sh phase12-widget-ability OnboardingWidgetPropsAbsent
```

## Related

- [[domains/ux-branding]] — the step that asks for the tenant's colours
- [[domains/http-layer]] — where the gate sits in the middleware stack
