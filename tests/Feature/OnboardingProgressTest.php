<?php

declare(strict_types=1);

use Inertia\Testing\AssertableInertia;

/**
 * Progress is derived, not recorded.
 *
 * Reading the checklist writes nothing. Whether a step is done is a question put
 * to the step, answered from the data the step is about, which is why branding
 * the organization from the settings screen counts immediately and why there is
 * no row to fall out of step with the truth.
 */
it('OnboardingProgressDerivedOnRead writes nothing while the checklist is read', function (): void {
    [$owner, $organization] = ownerBeforeOnboarding();

    $this->actingAs($owner)->get(route('onboarding.show'))->assertOk();

    $this->assertDatabaseCount('onboarding_progress', 0);

    $organization->forceFill(['brand_primary_color' => '#123456'])->save();

    $this->actingAs($owner->fresh())
        ->get(route('onboarding.show'))
        ->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
            ->where('checklist.steps.0.key', 'brand')
            ->where('checklist.steps.0.complete', true));

    $this->assertDatabaseCount('onboarding_progress', 0);
});
