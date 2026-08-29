<?php

declare(strict_types=1);

use App\Models\Organization;
use App\Models\OrganizationInvitation;
use App\Models\User;
use App\Notifications\OrganizationInvitationNotification;
use App\Support\OrganizationContext;
use Illuminate\Support\Facades\Notification as Notifications;

/**
 * Per-channel preferences. They filter what a notification already offers and
 * can never add a channel it does not support, so a new channel reaches people
 * without a backfill and an opt-out survives one.
 */
function member(): User
{
    return User::factory()->forOrganization(Organization::factory()->create())->create();
}

it('NotificationPreference shows every channel with nothing recorded as on', function (): void {
    $user = member();

    $this->actingAs($user)
        ->get(route('user-notification-preference.edit'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('user-notification-preference/edit')
            ->where('preferences.organization_invitation_notification.mail', true)
            ->where('preferences.organization_invitation_notification.database', true));
});

it('NotificationPreference stores an opt-out', function (): void {
    $user = member();

    $this->actingAs($user)
        ->from(route('user-notification-preference.edit'))
        ->patch(route('user-notification-preference.update'), [
            'organization_invitation_notification' => ['mail' => '0', 'database' => '1'],
        ])
        ->assertRedirect(route('user-notification-preference.edit'));

    expect($user->refresh()->notificationPreferences())
        ->toBe(['organization_invitation_notification' => ['mail' => false, 'database' => true]]);
});

it('NotificationPreference rejects a missing channel', function (): void {
    $user = member();

    $this->actingAs($user)
        ->patch(route('user-notification-preference.update'), [
            'organization_invitation_notification' => ['mail' => '1'],
        ])
        ->assertSessionHasErrors('organization_invitation_notification.database');
});

it('NotificationPreference keeps an opted-out channel out of the delivery', function (): void {
    Notifications::fake();

    $user = member();

    $user->forceFill([
        'notification_preferences' => [
            'organization_invitation_notification' => ['mail' => false, 'database' => true],
        ],
    ])->save();

    $organization = $user->currentOrganization;
    expect($organization)->not->toBeNull();

    resolve(OrganizationContext::class)->runAs($organization, function () use ($organization, $user): void {
        $invitation = OrganizationInvitation::factory()->create([
            'organization_id' => $organization->id,
        ]);

        $user->notify(new OrganizationInvitationNotification($invitation, 'token'));
    });

    Notifications::assertSentTo(
        $user,
        OrganizationInvitationNotification::class,
        fn (OrganizationInvitationNotification $notification, array $channels): bool => $channels === ['database'],
    );
});
