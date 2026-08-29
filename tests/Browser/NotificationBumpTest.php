<?php

declare(strict_types=1);

use App\Models\Organization;
use App\Models\OrganizationInvitation;
use App\Models\User;
use App\Notifications\OrganizationInvitationNotification;
use App\Support\OrganizationContext;

/**
 * The inbox in a real browser.
 *
 * The websocket only says "something happened here"; the numbers and the rows
 * come back from the server, scoped to the person asking. So what is worth
 * driving in a browser is the part a socket cannot fake: the badge appears with
 * the unread count, the panel lists that notification, and marking it read
 * clears both without a full page load.
 */
beforeEach(function (): void {
    $organization = Organization::factory()->create();

    $this->organization = $organization;

    $this->user = User::factory()->forOrganization($organization)->create()->fresh();

    $this->actingAs($this->user);
});

function raise(Organization $organization, User $user): void
{
    resolve(OrganizationContext::class)->runAs($organization, function () use ($organization, $user): void {
        $invitation = OrganizationInvitation::factory()->create([
            'organization_id' => $organization->id,
        ]);

        $user->notify(new OrganizationInvitationNotification($invitation, 'browser-token'));
    });
}

it('shows no badge when the inbox is empty', function (): void {
    visit('/dashboard')
        ->wait(1)
        ->assertPresent('[data-test="notification-bell"]')
        ->assertMissing('[data-test="notification-badge"]')
        ->assertNoJavaScriptErrors();
});

it('bumps the badge and lists the notification, then clears both when it is read', function (): void {
    raise($this->organization, $this->user);

    visit('/dashboard')
        ->wait(1)
        ->assertSeeIn('[data-test="notification-badge"]', '1')
        ->click('[data-test="notification-bell"]')
        ->waitForText('You have been invited to')
        ->assertPresent('[data-test="notification-panel"]')
        ->click('Mark read')
        ->wait(1)
        ->assertMissing('[data-test="notification-badge"]')
        ->assertNoJavaScriptErrors();
});
