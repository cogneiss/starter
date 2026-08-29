<?php

declare(strict_types=1);

use App\Models\Organization;
use App\Models\OrganizationInvitation;
use App\Models\User;
use App\Notifications\OrganizationInvitationNotification;

/**
 * A fresh clone has no Reverb credentials, and most deployments of a starter kit
 * never get any. Realtime is therefore an enhancement: with no broadcaster
 * configured the inbox still fills, the pages still render, and nothing anywhere
 * tries to open a socket.
 */
beforeEach(function (): void {
    config()->set('broadcasting.default', 'null');
    config()->set('broadcasting.connections.reverb.key', '');
    config()->set('broadcasting.connections.reverb.secret', '');
    config()->set('broadcasting.connections.reverb.app_id', '');
});

it('RealtimeNoKey delivers notifications with no broadcaster configured', function (): void {
    $organization = Organization::factory()->create();
    $user = User::factory()->forOrganization($organization)->create();

    $invitation = OrganizationInvitation::factory()->create([
        'organization_id' => $organization->id,
    ]);

    $user->notify(new OrganizationInvitationNotification($invitation, 'token'));

    expect($user->refresh()->unreadNotifications()->count())->toBe(1);
});

it('RealtimeNoKey renders the inbox with no broadcaster configured', function (): void {
    $organization = Organization::factory()->create();
    $user = User::factory()->forOrganization($organization)->create();

    $this->actingAs($user)
        ->get('/dashboard')
        ->assertOk()
        ->assertInertia(fn ($page) => $page->where('unreadNotifications', 0));
});

it('RealtimeNoKey leaves the broadcast keys blank in the example environment', function (): void {
    $example = (string) file_get_contents(base_path('.env.example'));

    foreach (['REVERB_APP_KEY', 'VITE_REVERB_APP_KEY'] as $key) {
        expect($example)->toMatch('/^'.$key.'=\s*$/m');
    }
});
