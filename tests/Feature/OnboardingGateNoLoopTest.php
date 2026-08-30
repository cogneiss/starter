<?php

declare(strict_types=1);

/**
 * The gate has to leave two doors open.
 *
 * A gate that redirects the screen it redirects to is an infinite loop, and a
 * gate that catches sign out traps a person inside an organization they cannot
 * finish setting up. Both are excluded by name.
 */
it('OnboardingGateNoLoop serves the onboarding screen it redirects to', function (): void {
    [$owner] = ownerBeforeOnboarding();

    $this->actingAs($owner)
        ->get(route('onboarding.show'))
        ->assertOk();
});

it('OnboardingGateNoLoop lets a gated person sign out', function (): void {
    [$owner] = ownerBeforeOnboarding();

    $this->actingAs($owner)
        ->post(route('logout'))
        ->assertRedirect('/');

    $this->assertGuest();
});
