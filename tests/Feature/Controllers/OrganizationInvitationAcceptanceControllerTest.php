<?php

declare(strict_types=1);

use App\Actions\SeedOrganizationRoles;
use App\Models\Organization;
use App\Models\OrganizationInvitation;
use App\Models\User;
use Illuminate\Support\Facades\Auth;

function pendingInvitation(string $email, string $token = 'raw-token'): OrganizationInvitation
{
    $organization = Organization::factory()->create();
    resolve(SeedOrganizationRoles::class)->handle($organization);

    return OrganizationInvitation::factory()->create([
        'organization_id' => $organization->id,
        'email' => $email,
        'role' => 'Member',
        'token' => hash('sha256', $token),
    ]);
}

it('shows a pending invitation to a guest', function (): void {
    $invitation = pendingInvitation('new@example.com');

    $this->get(route('organization-invitation-acceptance.show', ['token' => 'raw-token']))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('organization-invitation/show')
            ->where('email', 'new@example.com')
            ->where('organization', $invitation->organization->name)
            ->where('pending', true));
});

it('shows an unknown token as unavailable', function (): void {
    $this->get(route('organization-invitation-acceptance.show', ['token' => 'missing']))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->where('pending', false)->where('email', null));
});

it('sends a guest to log in before accepting', function (): void {
    pendingInvitation('new@example.com');

    $this->post(route('organization-invitation-acceptance.update', ['token' => 'raw-token']))
        ->assertRedirectToRoute('login');

    expect(session('url.intended'))
        ->toBe(route('organization-invitation-acceptance.show', ['token' => 'raw-token']));
});

it('accepts the invitation for the invited user', function (): void {
    $invitation = pendingInvitation('new@example.com');
    $user = User::factory()->create(['email' => 'new@example.com']);

    $this->actingAs($user)
        ->post(route('organization-invitation-acceptance.update', ['token' => 'raw-token']))
        ->assertRedirectToRoute('dashboard');

    expect($invitation->refresh()->accepted_at)->not->toBeNull()
        ->and($user->refresh()->current_organization_id)->toBe($invitation->organization_id);
});

it('logs out a different account instead of attaching it', function (): void {
    pendingInvitation('her@example.com');
    $user = User::factory()->create(['email' => 'him@example.com']);

    $this->actingAs($user)
        ->post(route('organization-invitation-acceptance.update', ['token' => 'raw-token']))
        ->assertRedirectToRoute('login');

    expect(Auth::check())->toBeFalse();
});

it('sends an expired invitation back to the unavailable page', function (): void {
    $invitation = pendingInvitation('late@example.com');
    $invitation->forceFill(['expires_at' => now()->subDay()])->save();

    $user = User::factory()->create(['email' => 'late@example.com']);

    $this->actingAs($user)
        ->post(route('organization-invitation-acceptance.update', ['token' => 'raw-token']))
        ->assertRedirectToRoute('organization-invitation-acceptance.show', ['token' => 'raw-token']);
});
