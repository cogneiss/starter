<?php

declare(strict_types=1);

use App\Actions\ReactivateOrganizationMembership;
use App\Actions\RemoveOrganizationMembership;
use App\Actions\SuspendOrganizationMembership;
use App\Actions\UpdateOrganizationMembershipRole;
use App\Enums\MembershipStatus;
use App\Models\Organization;
use App\Models\OrganizationMembership;
use App\Models\User;
use App\Support\OrganizationContext;
use Illuminate\Validation\ValidationException;

function membershipFor(Organization $organization, string $role = 'Member'): OrganizationMembership
{
    $user = User::factory()->forOrganization($organization, $role)->create();

    return $organization->memberships()->where('user_id', $user->id)->sole();
}

it('suspends and reactivates a member', function (): void {
    $organization = Organization::factory()->create();
    membershipFor($organization, 'Owner');
    $membership = membershipFor($organization);

    resolve(SuspendOrganizationMembership::class)->handle($membership);
    expect($membership->refresh()->status)->toBe(MembershipStatus::Suspended);

    resolve(ReactivateOrganizationMembership::class)->handle($membership);
    expect($membership->refresh()->status)->toBe(MembershipStatus::Active)
        ->and($membership->joined_at)->not->toBeNull();
});

it('keeps the original joined date when reactivating', function (): void {
    $organization = Organization::factory()->create();
    $membership = membershipFor($organization);
    $joined = $membership->joined_at;

    resolve(ReactivateOrganizationMembership::class)->handle($membership);

    expect($membership->refresh()->joined_at?->toDateTimeString())->toBe($joined?->toDateTimeString());
});

it('removes a member, their roles and their current organization pointer', function (): void {
    $organization = Organization::factory()->create();
    membershipFor($organization, 'Owner');
    $membership = membershipFor($organization);
    $user = $membership->user;

    resolve(RemoveOrganizationMembership::class)->handle($membership);

    expect(OrganizationMembership::query()->whereKey($membership->id)->exists())->toBeFalse()
        ->and($user->refresh()->current_organization_id)->toBeNull();

    resolve(OrganizationContext::class)->runAs($organization, function () use ($user): void {
        expect($user->fresh()?->roles)->toBeEmpty();
    });
});

it('changes a member role', function (): void {
    $organization = Organization::factory()->create();
    membershipFor($organization, 'Owner');
    $membership = membershipFor($organization);

    resolve(UpdateOrganizationMembershipRole::class)->handle($membership, 'Admin');

    resolve(OrganizationContext::class)->runAs($organization, function () use ($membership): void {
        expect($membership->user->fresh()?->hasRole('Admin'))->toBeTrue();
    });
});

it('refuses a role the organization does not have', function (): void {
    $organization = Organization::factory()->create();
    $membership = membershipFor($organization);

    expect(fn (): OrganizationMembership => resolve(UpdateOrganizationMembershipRole::class)->handle($membership, 'Wizard'))
        ->toThrow(ValidationException::class);
});

it('refuses to remove the last active owner', function (): void {
    $organization = Organization::factory()->create();
    $membership = membershipFor($organization, 'Owner');

    expect(fn () => resolve(RemoveOrganizationMembership::class)->handle($membership))
        ->toThrow(ValidationException::class);
});

it('refuses to suspend the last active owner', function (): void {
    $organization = Organization::factory()->create();
    $membership = membershipFor($organization, 'Owner');

    expect(fn (): OrganizationMembership => resolve(SuspendOrganizationMembership::class)->handle($membership))
        ->toThrow(ValidationException::class);
});

it('refuses to demote the last active owner', function (): void {
    $organization = Organization::factory()->create();
    $membership = membershipFor($organization, 'Owner');

    expect(fn (): OrganizationMembership => resolve(UpdateOrganizationMembershipRole::class)->handle($membership, 'Member'))
        ->toThrow(ValidationException::class);
});

it('allows the last owner to be promoted to owner again', function (): void {
    $organization = Organization::factory()->create();
    $membership = membershipFor($organization, 'Owner');

    resolve(UpdateOrganizationMembershipRole::class)->handle($membership, 'Owner');

    resolve(OrganizationContext::class)->runAs($organization, function () use ($membership): void {
        expect($membership->user->fresh()?->hasRole('Owner'))->toBeTrue();
    });
});

it('lets an owner go once a second owner exists', function (): void {
    $organization = Organization::factory()->create();
    $first = membershipFor($organization, 'Owner');
    membershipFor($organization, 'Owner');

    resolve(RemoveOrganizationMembership::class)->handle($first);

    expect(OrganizationMembership::query()->whereKey($first->id)->exists())->toBeFalse();
});

it('ignores the owner guard for a member who is already suspended', function (): void {
    $organization = Organization::factory()->create();
    $membership = membershipFor($organization, 'Owner');
    $membership->forceFill(['status' => MembershipStatus::Suspended])->save();

    resolve(RemoveOrganizationMembership::class)->handle($membership);

    expect(OrganizationMembership::query()->whereKey($membership->id)->exists())->toBeFalse();
});
