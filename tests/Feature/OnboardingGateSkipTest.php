<?php

declare(strict_types=1);

use App\Models\OnboardingProgress;

/**
 * Somebody who already knows the product can say so once.
 *
 * Skipping is a stored decision rather than a session flag, so it survives a new
 * browser and a week away. Asking again tomorrow is nagging.
 */
it('OnboardingGateSkip holds a new owner on the onboarding screen', function (): void {
    [$owner] = ownerBeforeOnboarding();

    $this->actingAs($owner)
        ->get(route('dashboard'))
        ->assertRedirect(route('onboarding.show'));
});

it('OnboardingGateSkip lets the gate go for good once it is skipped', function (): void {
    [$owner, $organization] = ownerBeforeOnboarding();

    $this->actingAs($owner)
        ->post(route('onboarding.skip'))
        ->assertRedirect(route('dashboard'));

    $this->assertDatabaseHas('onboarding_progress', [
        'organization_id' => $organization->id,
        'user_id' => $owner->id,
    ]);

    $this->actingAs($owner)
        ->get(route('dashboard'))
        ->assertOk();

    // Once, not once per visit.
    $this->actingAs($owner)->post(route('onboarding.skip'))->assertRedirect(route('dashboard'));

    expect(OnboardingProgress::withoutOrganizationScope()->count())->toBe(1);
});
