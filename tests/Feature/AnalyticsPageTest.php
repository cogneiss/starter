<?php

declare(strict_types=1);

use App\Models\Organization;
use App\Models\User;

it('shares the Do Not Track signal with every page when the browser sends it', function (): void {
    $user = User::factory()->forOrganization(Organization::factory()->create())->withoutTwoFactor()->create();

    $this->actingAs($user)
        ->withHeader('DNT', '1')
        ->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->where('doNotTrack', true));
});

it('shares it as false when the browser stays silent', function (): void {
    $user = User::factory()->forOrganization(Organization::factory()->create())->withoutTwoFactor()->create();

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->where('doNotTrack', false));
});
