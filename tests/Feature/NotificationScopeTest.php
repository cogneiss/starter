<?php

declare(strict_types=1);

use App\Models\Organization;
use App\Models\OrganizationInvitation;
use App\Models\OrganizationMembership;
use App\Models\User;
use App\Notifications\OrganizationInvitationNotification;
use App\Support\OrganizationContext;

/**
 * A person who belongs to two organizations has two inboxes, and the one they
 * see is the one they are acting in. The tenant is a column on the row and a
 * where clause on every read, so the wrong inbox is not filtered out of a
 * result — it is never selected.
 */

/**
 * A notification raised inside the given organization, as the application
 * raises it. Nothing here writes the row by hand: the organization is stamped
 * by the notification channel, and a test that stamped it itself would be
 * proving its own assertion.
 */
function invitationIn(Organization $organization): OrganizationInvitationNotification
{
    $invitation = OrganizationInvitation::factory()->create([
        'organization_id' => $organization->id,
    ]);

    return new OrganizationInvitationNotification($invitation, 'token');
}

/**
 * A member of two organizations, currently acting in the first.
 *
 * @return array{0: User, 1: Organization, 2: Organization}
 */
function memberOfTwo(): array
{
    $organization = Organization::factory()->create();
    $other = Organization::factory()->create();

    $user = User::factory()->forOrganization($organization)->create();

    OrganizationMembership::factory()->create([
        'organization_id' => $other->id,
        'user_id' => $user->id,
    ]);

    return [$user, $organization, $other];
}

// NotificationScope: the unread count behind the badge.
it('NotificationScope counts only the current organization in the unread badge count', function (): void {
    [$user, $organization, $other] = memberOfTwo();

    $context = resolve(OrganizationContext::class);

    $context->runAs($organization, fn () => $user->notify(invitationIn($organization)));
    $context->runAs($other, fn () => $user->notify(invitationIn($other)));
    $context->runAs($other, fn () => $user->notify(invitationIn($other)));

    $this->actingAs($user)
        ->get('/dashboard')
        ->assertOk()
        ->assertInertia(fn ($page) => $page->where('unreadNotifications', 1));
});

// NotificationScope: the rows in the panel, not merely how many there are.
it('NotificationScope lists only the current organization in the recentNotifications panel list', function (): void {
    [$user, $organization, $other] = memberOfTwo();

    $context = resolve(OrganizationContext::class);

    $context->runAs($organization, fn () => $user->notify(invitationIn($organization)));
    $context->runAs($other, fn () => $user->notify(invitationIn($other)));

    $mine = $user->unreadNotifications()
        ->getQuery()
        ->where('organization_id', $organization->id)
        ->sole();

    $this->actingAs($user)
        ->get('/dashboard')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('recentNotifications', 1)
            ->where('recentNotifications.0.id', (string) $mine->getKey()));
});

it('NotificationScope keeps a read notification out of both the count and the list', function (): void {
    [$user, $organization] = memberOfTwo();

    $context = resolve(OrganizationContext::class);

    $context->runAs($organization, fn () => $user->notify(invitationIn($organization)));

    $user->unreadNotifications()->getQuery()->update(['read_at' => now()]);

    $this->actingAs($user)
        ->get('/dashboard')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('unreadNotifications', 0)
            ->has('recentNotifications', 0));
});
