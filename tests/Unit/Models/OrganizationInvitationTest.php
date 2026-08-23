<?php

declare(strict_types=1);

use App\Models\Organization;
use App\Models\OrganizationInvitation;
use App\Models\User;

it('finds an invitation by its raw token', function (): void {
    $invitation = OrganizationInvitation::factory()->create(['token' => hash('sha256', 'raw-token')]);

    expect(OrganizationInvitation::findByToken('raw-token')?->id)->toBe($invitation->id)
        ->and(OrganizationInvitation::findByToken('other-token'))->toBeNull();
});

it('is pending only while unaccepted and unexpired', function (): void {
    expect(OrganizationInvitation::factory()->create()->isPending())->toBeTrue()
        ->and(OrganizationInvitation::factory()->expired()->create()->isPending())->toBeFalse()
        ->and(OrganizationInvitation::factory()->accepted()->create()->isPending())->toBeFalse();
});

it('belongs to an organization and an inviter', function (): void {
    $organization = Organization::factory()->create();
    $inviter = User::factory()->create();

    $invitation = OrganizationInvitation::factory()->create([
        'organization_id' => $organization->id,
        'invited_by_user_id' => $inviter->id,
    ]);

    expect($invitation->organization->id)->toBe($organization->id)
        ->and($invitation->invitedBy?->id)->toBe($inviter->id);
});
