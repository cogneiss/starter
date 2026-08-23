<?php

declare(strict_types=1);

use App\Models\Organization;
use App\Models\User;
use Inertia\Support\SessionKey;

function memberOfRequiringOrganization(bool $withTwoFactor = false): User
{
    $organization = Organization::factory()->create(['require_two_factor' => true]);

    $factory = User::factory()->forOrganization($organization);

    return ($withTwoFactor ? $factory : $factory->withoutTwoFactor())->create();
}

it('sends a member without two-factor authentication to the setup screen', function (): void {
    $this->actingAs(memberOfRequiringOrganization())
        ->get(route('dashboard'))
        ->assertRedirectToRoute('two-factor.show')
        ->assertSessionHas(SessionKey::FLASH_DATA, [
            'toast' => [
                'type' => 'error',
                'message' => __('Your organization requires two-factor authentication.'),
            ],
        ]);
});

it('keeps the setup screen reachable', function (): void {
    $this->actingAs(memberOfRequiringOrganization())
        ->session(['auth.password_confirmed_at' => time()])
        ->get(route('two-factor.show'))
        ->assertOk();
});

it('keeps logging out reachable', function (): void {
    $this->actingAs(memberOfRequiringOrganization())
        ->post(route('logout'))
        ->assertRedirect();

    $this->assertGuest();
});

it('lets a member with confirmed two-factor authentication through', function (): void {
    $this->actingAs(memberOfRequiringOrganization(withTwoFactor: true))
        ->get(route('dashboard'))
        ->assertOk();
});

it('does nothing when the organization does not require two-factor authentication', function (): void {
    $organization = Organization::factory()->create();
    $user = User::factory()->forOrganization($organization)->withoutTwoFactor()->create();

    $this->actingAs($user)->get(route('dashboard'))->assertOk();
});

it('does nothing when no organization is resolved', function (): void {
    $this->actingAs(User::factory()->withoutTwoFactor()->create())
        ->get(route('user-profile.edit'))
        ->assertOk();
});
