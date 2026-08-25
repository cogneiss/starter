<?php

declare(strict_types=1);

use App\Actions\ConsumeConfirmToken;
use App\Actions\CreateConfirmToken;
use App\Ai\Actions\InviteMember;
use App\Data\AiConfirmTokenData;
use App\Data\InviteMemberData;
use App\Exceptions\InvalidConfirmToken;
use App\Models\AiConfirmToken;
use App\Models\Organization;
use App\Models\OrganizationInvitation;
use App\Models\User;
use App\Support\OrganizationContext;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Notification;
use Illuminate\Testing\TestResponse;
use Illuminate\Validation\ValidationException;
use Inertia\Support\SessionKey;

/**
 * A member who may invite, and a proposal waiting for them.
 *
 * @return array{0: Organization, 1: User, 2: AiConfirmToken}
 */
function pendingConfirmToken(string $email = 'proposed@example.com'): array
{
    $organization = Organization::factory()->create();
    $owner = User::factory()->forOrganization($organization)->create();

    $token = resolve(OrganizationContext::class)->runAs(
        $organization,
        fn (): AiConfirmToken => resolve(CreateConfirmToken::class)->handle(
            $owner,
            'invite-member',
            ['email' => $email, 'role' => 'Member'],
        ),
    );

    return [$organization, $owner, $token];
}

/**
 * A correctly signed token naming an action key, whatever that key is. The
 * signature has to be valid or the action lookup is never reached.
 */
function signedConfirmTokenFor(Organization $organization, User $user, string $action): AiConfirmToken
{
    $token = AiConfirmToken::factory()->for($organization)->for($user)->create(['action' => $action]);

    $token->forceFill([
        'signature' => AiConfirmToken::signatureFor($token->id, $action, $token->payload),
    ])->save();

    return $token;
}

function consumeConfirmTokenAs(Organization $organization, User $user, AiConfirmToken $token): mixed
{
    return resolve(OrganizationContext::class)->runAs(
        $organization,
        fn (): mixed => resolve(ConsumeConfirmToken::class)->handle($token->id, $user),
    );
}

beforeEach(function (): void {
    Notification::fake();
});

it('refuses a replay of a consumed token, executing the action once', function (): void {
    [$organization, $owner, $token] = pendingConfirmToken();

    consumeConfirmTokenAs($organization, $owner, $token);

    expect(fn (): mixed => consumeConfirmTokenAs($organization, $owner, $token))
        ->toThrow(InvalidConfirmToken::class, 'already been used');

    expect(OrganizationInvitation::withoutOrganizationScope()
        ->where('email', 'proposed@example.com')
        ->count())->toBe(1);
});

it('refuses a tampered payload', function (): void {
    [$organization, $owner, $token] = pendingConfirmToken();

    // The signature covers the payload, so editing the row after it was signed
    // is what fails — not the encryption, which re-encrypts happily.
    $token->forceFill(['payload' => ['email' => 'attacker@example.com', 'role' => 'Owner']])->save();

    expect(fn (): mixed => consumeConfirmTokenAs($organization, $owner, $token))
        ->toThrow(InvalidConfirmToken::class, 'no longer matches');

    expect(OrganizationInvitation::withoutOrganizationScope()->count())->toBe(0);
});

it('refuses an expired token', function (): void {
    [$organization, $owner, $token] = pendingConfirmToken();

    $this->travelTo(now()->addMinutes(config()->integer('ai.confirm.ttl') + 1));

    expect(fn (): mixed => consumeConfirmTokenAs($organization, $owner, $token))
        ->toThrow(InvalidConfirmToken::class, 'expired');

    expect(OrganizationInvitation::withoutOrganizationScope()->count())->toBe(0);
});

it('refuses a consume by the wrong user', function (): void {
    [$organization, , $token] = pendingConfirmToken();

    $other = User::factory()->forOrganization($organization)->create();

    expect(fn (): mixed => consumeConfirmTokenAs($organization, $other, $token))
        ->toThrow(InvalidConfirmToken::class, 'Only the person who was shown');

    expect(OrganizationInvitation::withoutOrganizationScope()->count())->toBe(0);
});

it('refuses a consume under the wrong organization', function (): void {
    [, $owner, $token] = pendingConfirmToken();

    $elsewhere = Organization::factory()->create();

    expect(fn (): mixed => consumeConfirmTokenAs($elsewhere, $owner, $token))
        ->toThrow(InvalidConfirmToken::class, 'different organization');

    expect(OrganizationInvitation::withoutOrganizationScope()->count())->toBe(0);
});

it('refuses a consume after the permission was revoked', function (): void {
    [$organization, $owner, $token] = pendingConfirmToken();

    resolve(OrganizationContext::class)->runAs(
        $organization,
        fn (): User => $owner->syncRoles([]),
    );

    expect(fn (): mixed => consumeConfirmTokenAs($organization, $owner, $token))
        ->toThrow(AuthorizationException::class);

    expect(OrganizationInvitation::withoutOrganizationScope()->count())->toBe(0);
});

it('refuses an unmapped action key', function (): void {
    $organization = Organization::factory()->create();
    $owner = User::factory()->forOrganization($organization)->create();

    $token = signedConfirmTokenFor($organization, $owner, 'delete-organization');

    expect(fn (): mixed => consumeConfirmTokenAs($organization, $owner, $token))
        ->toThrow(InvalidConfirmToken::class, 'not one an agent may propose');
});

it('refuses an action key that is a fully qualified class name', function (): void {
    $organization = Organization::factory()->create();
    $owner = User::factory()->forOrganization($organization)->create();

    $token = signedConfirmTokenFor($organization, $owner, InviteMember::class);

    expect(fn (): mixed => consumeConfirmTokenAs($organization, $owner, $token))
        ->toThrow(InvalidConfirmToken::class, 'not one an agent may propose');

    expect(OrganizationInvitation::withoutOrganizationScope()->count())->toBe(0);
});

it('refuses to propose an unmapped action key', function (): void {
    $organization = Organization::factory()->create();
    $owner = User::factory()->forOrganization($organization)->create();

    expect(fn (): AiConfirmToken => resolve(OrganizationContext::class)->runAs(
        $organization,
        fn (): AiConfirmToken => resolve(CreateConfirmToken::class)->handle($owner, 'delete-organization', []),
    ))->toThrow(InvalidConfirmToken::class);
});

it('refuses to propose an action the member may not run', function (): void {
    $organization = Organization::factory()->create();
    $member = User::factory()->forOrganization($organization, 'Member')->create();

    expect(fn (): AiConfirmToken => resolve(OrganizationContext::class)->runAs(
        $organization,
        fn (): AiConfirmToken => resolve(CreateConfirmToken::class)->handle(
            $member,
            'invite-member',
            ['email' => 'proposed@example.com', 'role' => 'Member'],
        ),
    ))->toThrow(AuthorizationException::class);
});

it('refuses a token id that does not exist', function (): void {
    $organization = Organization::factory()->create();
    $owner = User::factory()->forOrganization($organization)->create();

    expect(fn (): mixed => resolve(OrganizationContext::class)->runAs(
        $organization,
        fn (): mixed => resolve(ConsumeConfirmToken::class)->handle('00000000-0000-0000-0000-000000000000', $owner),
    ))->toThrow(InvalidConfirmToken::class, 'no confirmation waiting');
});

it('summarises the proposal in a sentence a person can answer', function (): void {
    [, , $token] = pendingConfirmToken();

    expect($token->summary)->toBe('Invite proposed@example.com as Member.')
        ->and($token->consumed_at)->toBeNull()
        ->and($token->hasValidSignature())->toBeTrue();
});

it('validates the payload against the action data object', function (): void {
    $organization = Organization::factory()->create();
    $owner = User::factory()->forOrganization($organization)->create();

    expect(fn (): AiConfirmToken => resolve(OrganizationContext::class)->runAs(
        $organization,
        fn (): AiConfirmToken => resolve(CreateConfirmToken::class)->handle(
            $owner,
            'invite-member',
            ['email' => 'not-an-email', 'role' => 'Member'],
        ),
    ))->toThrow(ValidationException::class);
});

it('shows a pending confirmation only to the person it was raised for', function (): void {
    [$organization, $owner, $token] = pendingConfirmToken();

    $other = User::factory()->forOrganization($organization)->create();
    $elsewhere = Organization::factory()->create();

    resolve(OrganizationContext::class)->runAs($organization, function () use ($owner, $other, $token): void {
        expect($owner->can('viewAny', AiConfirmToken::class))->toBeTrue()
            ->and($owner->can('view', $token))->toBeTrue()
            ->and($other->can('view', $token))->toBeFalse();
    });

    resolve(OrganizationContext::class)->runAs($elsewhere, function () use ($owner, $token): void {
        expect($owner->can('view', $token))->toBeFalse();
    });

    expect($owner->can('viewAny', AiConfirmToken::class))->toBeFalse();
});

it('refuses the same token posted twice over HTTP, inviting once', function (): void {
    [, $owner, $token] = pendingConfirmToken('replayed@example.com');

    $post = fn (): TestResponse => $this->actingAs($owner)
        ->from(route('dashboard'))
        ->post(route('ai-confirm.store', ['token' => $token->id]));

    $post()->assertRedirect();

    // The second post is the replay: same token, same user, moments later. It
    // is refused as an ordinary answer, not a server fault.
    $replayed = $post()->assertRedirect();

    expect($replayed->getSession()->get(SessionKey::FLASH_DATA)['toast'])
        ->toBe(['type' => 'error', 'message' => 'That confirmation has already been used.']);

    expect(OrganizationInvitation::withoutOrganizationScope()
        ->where('email', 'replayed@example.com')
        ->count())->toBe(1);
});

it('describes a pending confirmation to the client without its payload', function (): void {
    [$organization, , $token] = pendingConfirmToken();

    $data = resolve(OrganizationContext::class)->runAs(
        $organization,
        fn (): AiConfirmTokenData => AiConfirmTokenData::from($token),
    );

    expect($data->id)->toBe($token->id)
        ->and($data->action)->toBe('invite-member')
        ->and($data->summary)->toBe($token->summary)
        ->and($data->expires_at)->toBe($token->expires_at->toIso8601String())
        ->and($data->toArray())->not->toHaveKey('payload');
});

it('refuses to invite when no organization is bound to the context', function (): void {
    $organization = Organization::factory()->create();
    $owner = User::factory()->forOrganization($organization)->create();

    resolve(OrganizationContext::class)->forget();

    expect(fn (): OrganizationInvitation => resolve(InviteMember::class)->confirm(
        $owner,
        InviteMemberData::from(['email' => 'nobody@example.com', 'role' => 'Member']),
    ))->toThrow(RuntimeException::class, 'needs an organization bound');

    expect(OrganizationInvitation::withoutOrganizationScope()->count())->toBe(0);
});

it('runs the action on the happy path through HTTP, exactly once', function (): void {
    [, $owner, $token] = pendingConfirmToken();

    $this->actingAs($owner)
        ->from(route('dashboard'))
        ->post(route('ai-confirm.store', ['token' => $token->id]))
        ->assertRedirect();

    expect(OrganizationInvitation::withoutOrganizationScope()
        ->where('email', 'proposed@example.com')
        ->count())->toBe(1)
        ->and($token->fresh()?->consumed_at)->not->toBeNull();
});
