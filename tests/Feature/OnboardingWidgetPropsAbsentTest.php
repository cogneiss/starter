<?php

declare(strict_types=1);

use App\Models\Organization;
use App\Models\User;
use Inertia\Testing\AssertableInertia;

/**
 * A widget somebody may not see is not in the props.
 *
 * Hiding it in the client would still send the numbers to the browser, where
 * they are one keystroke away in the network tab. The ability decides whether
 * the data is gathered at all, so the absence below is the whole control.
 */
it('OnboardingWidgetPropsAbsent leaves a widget out of the props when the ability is missing', function (): void {
    $organization = Organization::factory()->create();
    $member = User::factory()->forOrganization($organization, 'Member')->create();

    $this->actingAs($member)
        ->get(route('dashboard'))
        ->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
            ->component('dashboard')
            ->has('widgets.members')
            ->missing('widgets.invitations'));
});

it('OnboardingWidgetPropsAbsent includes every widget the ability allows', function (): void {
    $organization = Organization::factory()->create();
    $owner = User::factory()->forOrganization($organization)->create();

    $this->actingAs($owner)
        ->get(route('dashboard'))
        ->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
            ->where('widgets.members.value', 1)
            ->where('widgets.invitations.value', 0));
});
