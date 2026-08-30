<?php

declare(strict_types=1);

use App\Models\OrganizationInvitation;
use App\Support\OrganizationContext;
use Inertia\Testing\AssertableInertia;

/**
 * Onboarding is resumed, never restarted.
 *
 * Nothing records which step somebody is on. Each step answers whether it is
 * done from the data it is about, so leaving halfway through and coming back a
 * day later picks up at the first thing still outstanding — and a step finished
 * through its ordinary settings screen counts without telling anyone.
 */
it('OnboardingGateResume points at the first step that is still outstanding', function (): void {
    [$owner, $organization] = ownerBeforeOnboarding();

    $this->actingAs($owner)
        ->get(route('onboarding.show'))
        ->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
            ->component('onboarding/show')
            ->where('checklist.next', 'brand')
            ->where('checklist.complete', false));

    $organization->forceFill(['brand_primary_color' => '#123456'])->save();

    $this->actingAs($owner->fresh())
        ->get(route('onboarding.show'))
        ->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page->where('checklist.next', 'invite'));

    $this->actingAs($owner->fresh())
        ->get(route('dashboard'))
        ->assertRedirect(route('onboarding.show'));
});

it('OnboardingGateResume opens the application once the required steps are behind you', function (): void {
    [$owner, $organization] = ownerBeforeOnboarding();

    $organization->forceFill(['brand_primary_color' => '#123456'])->save();

    resolve(OrganizationContext::class)->runAs($organization, function () use ($organization): void {
        OrganizationInvitation::factory()->create(['organization_id' => $organization->id]);
    });

    $this->actingAs($owner)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page->where('checklist.complete', true));
});
