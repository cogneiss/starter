<?php

declare(strict_types=1);

use App\Actions\AcceptOrganizationInvitation;
use App\Actions\CreateOrganizationInvitation;
use App\Actions\ResendOrganizationInvitation;
use App\Actions\RevokeOrganizationInvitation;
use App\Actions\SeedOrganizationRoles;
use App\Enums\MembershipStatus;
use App\Models\Organization;
use App\Models\OrganizationInvitation;
use App\Models\User;
use App\Notifications\OrganizationInvitationNotification;
use App\Support\OrganizationContext;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;

function invite(Organization $organization, string $email, string $role = 'Member'): OrganizationInvitation
{
    $owner = User::factory()->forOrganization($organization)->create();

    return resolve(OrganizationContext::class)->runAs(
        $organization,
        fn (): OrganizationInvitation => resolve(CreateOrganizationInvitation::class)
            ->handle($organization, $owner, $email, $role),
    );
}

it('invites an email address and mails the link', function (): void {
    Notification::fake();

    $organization = Organization::factory()->create();
    resolve(SeedOrganizationRoles::class)->handle($organization);

    $invitation = invite($organization, 'new@example.com');

    expect($invitation->email)->toBe('new@example.com')
        ->and($invitation->role)->toBe('Member')
        ->and($invitation->isPending())->toBeTrue();

    Notification::assertSentOnDemand(OrganizationInvitationNotification::class);
});

it('refuses to invite somebody who is already a member', function (): void {
    Notification::fake();

    $organization = Organization::factory()->create();
    $member = User::factory()->forOrganization($organization)->create();

    expect(fn (): OrganizationInvitation => invite($organization, $member->email))
        ->toThrow(ValidationException::class);
});

it('resends the pending invitation instead of creating a second one', function (): void {
    Notification::fake();

    $organization = Organization::factory()->create();
    resolve(SeedOrganizationRoles::class)->handle($organization);

    $first = invite($organization, 'new@example.com');
    $second = invite($organization, 'new@example.com');

    expect($second->id)->toBe($first->id)
        ->and($second->token)->not->toBe($first->token)
        ->and(OrganizationInvitation::withoutOrganizationScope()->count())->toBe(1);
});

it('refuses to resend an invitation that was already accepted', function (): void {
    $invitation = OrganizationInvitation::factory()->accepted()->create();

    expect(fn (): OrganizationInvitation => resolve(ResendOrganizationInvitation::class)->handle($invitation))
        ->toThrow(ValidationException::class);
});

it('revokes an invitation', function (): void {
    $invitation = OrganizationInvitation::factory()->create();

    resolve(RevokeOrganizationInvitation::class)->handle($invitation);

    expect(OrganizationInvitation::withoutOrganizationScope()->count())->toBe(0);
});

it('accepts an invitation and joins the organization', function (): void {
    Notification::fake();

    $organization = Organization::factory()->create();
    resolve(SeedOrganizationRoles::class)->handle($organization);

    $invitation = invite($organization, 'new@example.com');
    $user = User::factory()->create(['email' => 'new@example.com', 'current_organization_id' => null]);

    $membership = resolve(AcceptOrganizationInvitation::class)->handle($invitation, $user);

    expect($membership->status)->toBe(MembershipStatus::Active)
        ->and($invitation->refresh()->accepted_at)->not->toBeNull()
        ->and($user->refresh()->current_organization_id)->toBe($organization->id);

    resolve(OrganizationContext::class)->runAs($organization, function () use ($user): void {
        expect($user->fresh()?->hasRole('Member'))->toBeTrue();
    });
});

it('leaves the current organization alone when the user already has one', function (): void {
    Notification::fake();

    $existing = Organization::factory()->create();
    $user = User::factory()->forOrganization($existing)->create();

    $organization = Organization::factory()->create();
    resolve(SeedOrganizationRoles::class)->handle($organization);
    $invitation = invite($organization, $user->email);

    resolve(AcceptOrganizationInvitation::class)->handle($invitation, $user);

    expect($user->refresh()->current_organization_id)->toBe($existing->id);
});

it('accepts an invitation for a role the organization no longer has', function (): void {
    Notification::fake();

    $organization = Organization::factory()->create();
    $invitation = OrganizationInvitation::factory()->create([
        'organization_id' => $organization->id,
        'email' => 'ghost@example.com',
        'role' => 'Nonexistent',
    ]);
    $user = User::factory()->create(['email' => 'ghost@example.com']);

    $membership = resolve(AcceptOrganizationInvitation::class)->handle($invitation, $user);

    expect($membership->organization_id)->toBe($organization->id);
});

it('refuses an expired invitation', function (): void {
    $invitation = OrganizationInvitation::factory()->expired()->create(['email' => 'late@example.com']);
    $user = User::factory()->create(['email' => 'late@example.com']);

    expect(fn () => resolve(AcceptOrganizationInvitation::class)->handle($invitation, $user))
        ->toThrow(ValidationException::class);
});

it('refuses an invitation that was already accepted', function (): void {
    $invitation = OrganizationInvitation::factory()->accepted()->create(['email' => 'again@example.com']);
    $user = User::factory()->create(['email' => 'again@example.com']);

    expect(fn () => resolve(AcceptOrganizationInvitation::class)->handle($invitation, $user))
        ->toThrow(ValidationException::class);
});

it('refuses an invitation addressed to another email', function (): void {
    $invitation = OrganizationInvitation::factory()->create(['email' => 'her@example.com']);
    $user = User::factory()->create(['email' => 'him@example.com']);

    expect(fn () => resolve(AcceptOrganizationInvitation::class)->handle($invitation, $user))
        ->toThrow(ValidationException::class);
});
