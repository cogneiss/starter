<?php

declare(strict_types=1);

use App\Models\Organization;
use App\Models\User;

it('tells the front end when the browser asks not to be tracked', function (): void {
    $user = User::factory()->forOrganization(Organization::factory()->create())->withoutTwoFactor()->create();

    $this->actingAs($user)
        ->withHeader('DNT', '1')
        ->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->where('doNotTrack', true));
});

it('does not claim the preference when the header is absent', function (): void {
    $user = User::factory()->forOrganization(Organization::factory()->create())->withoutTwoFactor()->create();

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->where('doNotTrack', false));
});
