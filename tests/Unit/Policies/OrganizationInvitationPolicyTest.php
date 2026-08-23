<?php

declare(strict_types=1);

use App\Models\Organization;
use App\Models\OrganizationInvitation;
use App\Models\User;
use App\Support\OrganizationContext;
use Illuminate\Support\Facades\Gate;

it('lets an owner manage invitations of their own organization', function (): void {
    $organization = Organization::factory()->create();
    $user = User::factory()->forOrganization($organization)->create();
    $invitation = OrganizationInvitation::factory()->create(['organization_id' => $organization->id]);

    resolve(OrganizationContext::class)->runAs($organization, function () use ($user, $invitation): void {
        expect(Gate::forUser($user)->allows('view', $invitation))->toBeTrue()
            ->and(Gate::forUser($user)->allows('update', $invitation))->toBeTrue()
            ->and(Gate::forUser($user)->allows('delete', $invitation))->toBeTrue();
    });
});

it('refuses a member without the invite permission', function (): void {
    $organization = Organization::factory()->create();
    $user = User::factory()->forOrganization($organization, 'Member')->create();
    $invitation = OrganizationInvitation::factory()->create(['organization_id' => $organization->id]);

    resolve(OrganizationContext::class)->runAs($organization, function () use ($user, $invitation): void {
        expect(Gate::forUser($user)->allows('view', $invitation))->toBeTrue()
            ->and(Gate::forUser($user)->allows('update', $invitation))->toBeFalse()
            ->and(Gate::forUser($user)->allows('delete', $invitation))->toBeFalse();
    });
});

it('refuses an invitation from another organization', function (): void {
    $organization = Organization::factory()->create();
    $user = User::factory()->forOrganization($organization)->create();
    $invitation = OrganizationInvitation::factory()->create();

    resolve(OrganizationContext::class)->runAs($organization, function () use ($user, $invitation): void {
        expect(Gate::forUser($user)->allows('view', $invitation))->toBeFalse();
    });
});
