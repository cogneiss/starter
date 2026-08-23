<?php

declare(strict_types=1);

use App\Models\OrganizationInvitation;
use App\Models\User;
use App\Notifications\OrganizationInvitationNotification;

it('is delivered by mail', function (): void {
    $invitation = OrganizationInvitation::factory()->create();

    expect(new OrganizationInvitationNotification($invitation, 'token')->via(User::factory()->create()))
        ->toBe(['mail']);
});

it('links to the acceptance route', function (): void {
    $invitation = OrganizationInvitation::factory()->create();

    $mail = new OrganizationInvitationNotification($invitation, 'token')->toMail(User::factory()->create());

    expect($mail->actionUrl)->toBe(route('organization-invitation-acceptance.show', ['token' => 'token']));
});
