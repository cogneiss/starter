<?php

declare(strict_types=1);

use App\Models\Organization;
use App\Models\User;
use Illuminate\Support\Facades\Auth;

it('ends the session of a deactivated account on its next request', function (): void {
    $user = User::factory()->forOrganization(Organization::factory()->create())->withoutTwoFactor()->create(['is_active' => false]);

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertRedirectToRoute('login')
        ->assertSessionHas('status', __('Your account has been deactivated.'));

    expect(Auth::check())->toBeFalse();
});

it('leaves an active account alone', function (): void {
    $user = User::factory()->forOrganization(Organization::factory()->create())->withoutTwoFactor()->create();

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertOk();

    expect(Auth::check())->toBeTrue();
});
