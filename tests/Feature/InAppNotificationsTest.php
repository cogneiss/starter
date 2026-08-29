<?php

declare(strict_types=1);

use App\Models\Organization;
use App\Models\OrganizationInvitation;
use App\Models\User;
use App\Notifications\OrganizationInvitationNotification;
use App\Support\OrganizationContext;

/**
 * The inbox itself: what the panel is given, and what marking a row read does.
 * Reads and writes both go through a query carrying the person and the
 * organization, so an id from elsewhere is not found rather than refused.
 */

/**
 * @return array{0: User, 1: Organization}
 */
function memberWithNotification(): array
{
    $organization = Organization::factory()->create();
    $user = User::factory()->forOrganization($organization)->create();

    resolve(OrganizationContext::class)->runAs($organization, function () use ($organization, $user): void {
        $invitation = OrganizationInvitation::factory()->create([
            'organization_id' => $organization->id,
        ]);

        $user->notify(new OrganizationInvitationNotification($invitation, 'token'));
    });

    return [$user, $organization];
}

it('InAppNotifications shares the unread rows with every page', function (): void {
    [$user] = memberWithNotification();

    $this->actingAs($user)
        ->get('/dashboard')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('unreadNotifications', 1)
            ->has('recentNotifications.0.title')
            ->has('recentNotifications.0.url')
            ->has('recentNotifications.0.created_at'));
});

it('InAppNotifications marks one notification read', function (): void {
    [$user] = memberWithNotification();

    $notification = $user->unreadNotifications()->sole();

    $this->actingAs($user)
        ->from('/dashboard')
        ->patch(route('notification.update', ['notification' => $notification->getKey()]))
        ->assertRedirect('/dashboard');

    expect($user->unreadNotifications()->count())->toBe(0);
});

it('InAppNotifications marks every notification in the organization read', function (): void {
    [$user, $organization] = memberWithNotification();

    resolve(OrganizationContext::class)->runAs($organization, function () use ($organization, $user): void {
        $invitation = OrganizationInvitation::factory()->create([
            'organization_id' => $organization->id,
        ]);

        $user->notify(new OrganizationInvitationNotification($invitation, 'second'));
    });

    $this->actingAs($user)
        ->from('/dashboard')
        ->patch(route('notification.update-all'))
        ->assertRedirect('/dashboard');

    expect($user->unreadNotifications()->count())->toBe(0);
});

it('InAppNotifications does not find another person notification', function (): void {
    [$user, $organization] = memberWithNotification();

    $stranger = User::factory()->forOrganization($organization)->create();
    $notification = $user->unreadNotifications()->sole();

    $this->actingAs($stranger)
        ->patch(route('notification.update', ['notification' => $notification->getKey()]))
        ->assertNotFound();

    expect($user->unreadNotifications()->count())->toBe(1);
});
