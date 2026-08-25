<?php

declare(strict_types=1);

use App\Actions\ConsumeConfirmToken;
use App\Ai\Agents\InvitationDrafter;
use App\Ai\Blocks\ConfirmBlock;
use App\Models\AiConfirmToken;
use App\Models\Organization;
use App\Models\User;
use App\Support\OrganizationContext;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;

/**
 * A member who may invite people, in a fresh organization.
 *
 * @return array{0: User, 1: Organization}
 */
function drafterMember(): array
{
    $organization = Organization::factory()->create();
    $owner = User::factory()->forOrganization($organization, 'Owner')->create();

    return [$owner, $organization];
}

/**
 * Draft an invitation the way the product does, inside the organization the
 * confirm token belongs to.
 */
function draftInvitation(User $user, Organization $organization, string $request): ConfirmBlock
{
    return resolve(OrganizationContext::class)->runAs(
        $organization,
        fn (): ConfirmBlock => new InvitationDrafter($user, $organization)->draft($request),
    );
}

it('abandons the draft when nobody approves it', function (): void {
    Mail::fake();
    Notification::fake();

    [$user, $organization] = drafterMember();

    InvitationDrafter::fake([['email' => 'taylor@example.com', 'role' => 'member']])
        ->preventStrayPrompts();

    $block = draftInvitation($user, $organization, 'invite taylor as a member');

    expect($block->token)->not->toBeEmpty();

    // The draft is a proposal and stays one. Nobody was told anything, and the
    // invitations table never heard about it.
    Mail::assertNothingSent();
    Notification::assertNothingSent();

    $this->assertDatabaseCount('organization_invitations', 0);
});

it('creates the invitation once the person confirms it', function (): void {
    Notification::fake();

    [$user, $organization] = drafterMember();

    InvitationDrafter::fake([['email' => 'taylor@example.com', 'role' => 'member']])
        ->preventStrayPrompts();

    $block = draftInvitation($user, $organization, 'invite taylor as a member');

    resolve(OrganizationContext::class)->runAs(
        $organization,
        fn (): mixed => resolve(ConsumeConfirmToken::class)->handle($block->token, $user),
    );

    $this->assertDatabaseCount('organization_invitations', 1);

    $this->assertDatabaseHas('organization_invitations', [
        'organization_id' => $organization->id,
        'email' => 'taylor@example.com',
    ]);
});

it('refuses to draft for someone who is not a member', function (): void {
    $organization = Organization::factory()->create();
    $stranger = User::factory()->create();

    expect(fn (): InvitationDrafter => new InvitationDrafter($stranger, $organization))
        ->toThrow(Illuminate\Auth\Access\AuthorizationException::class);
});

it('proposes only the action key the allowlist knows', function (): void {
    [$user, $organization] = drafterMember();

    InvitationDrafter::fake([['email' => 'taylor@example.com', 'role' => 'member']])
        ->preventStrayPrompts();

    draftInvitation($user, $organization, 'invite taylor as a member');

    $token = AiConfirmToken::withoutOrganizationScope()->sole();

    expect($token->action)->toBe(InvitationDrafter::ACTION)
        ->and($token->payload)->toBe(['email' => 'taylor@example.com', 'role' => 'member'])
        ->and($token->consumed_at)->toBeNull();
});
