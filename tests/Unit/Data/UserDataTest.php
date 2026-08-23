<?php

declare(strict_types=1);

use App\Data\BrowserSessionData;
use App\Data\ImpersonatorData;
use App\Data\LoginHistoryData;
use App\Data\OrganizationData;
use App\Data\OrganizationInvitationData;
use App\Data\OrganizationMemberData;
use App\Data\PasskeyData;
use App\Data\UserData;
use App\Enums\MembershipStatus;
use App\Models\LoginHistory;
use App\Models\Organization;
use App\Models\OrganizationInvitation;
use App\Models\OrganizationMembership;
use App\Models\User;
use Laravel\Passkeys\Passkey;

it('builds the shared user payload without leaking credentials', function (): void {
    $user = User::factory()->create(['name' => 'Ada', 'email' => 'ada@example.com']);

    $payload = UserData::fromModel($user)->toArray();

    expect($payload)->toBe([
        'id' => $user->id,
        'name' => 'Ada',
        'email' => 'ada@example.com',
        'email_verified_at' => $user->email_verified_at?->toIso8601String(),
        'two_factor_enabled' => true,
        'created_at' => $user->created_at->toIso8601String(),
        'updated_at' => $user->updated_at->toIso8601String(),
    ]);
});

it('builds the impersonator payload', function (): void {
    $user = User::factory()->create(['name' => 'Grace']);

    expect(ImpersonatorData::fromModel($user)->toArray())
        ->toBe(['id' => $user->id, 'name' => 'Grace']);
});

it('builds the organization payload', function (): void {
    $organization = Organization::factory()->create(['name' => 'Acme', 'slug' => 'acme']);

    expect(OrganizationData::fromModel($organization)->toArray())->toBe([
        'id' => $organization->id,
        'name' => 'Acme',
        'slug' => 'acme',
        'personal' => $organization->personal,
        'require_two_factor' => $organization->require_two_factor,
    ]);
});

it('builds the member payload from the membership and its user', function (): void {
    $membership = OrganizationMembership::factory()
        ->for(User::factory()->create(['name' => 'Alan', 'email' => 'alan@example.com']))
        ->create(['status' => MembershipStatus::Active]);

    expect(OrganizationMemberData::fromModel($membership)->toArray())->toBe([
        'id' => $membership->id,
        'name' => 'Alan',
        'email' => 'alan@example.com',
        'status' => 'active',
        'role' => null,
    ]);
});

it('builds the invitation payload with a date-only expiry', function (): void {
    $invitation = OrganizationInvitation::factory()->create(['email' => 'new@example.com', 'role' => 'Member']);

    expect(OrganizationInvitationData::fromModel($invitation)->toArray())->toBe([
        'id' => $invitation->id,
        'email' => 'new@example.com',
        'role' => 'Member',
        'expires_at' => $invitation->expires_at->toDateString(),
    ]);
});

it('builds the login history payload with a human device label', function (): void {
    $login = LoginHistory::factory()->create([
        'ip_address' => '127.0.0.1',
        'user_agent' => 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) Chrome/120.0 Safari/537.36',
        'successful' => true,
    ]);

    expect(LoginHistoryData::fromModel($login)->toArray())->toBe([
        'id' => $login->id,
        'device' => 'Chrome on macOS',
        'ip_address' => '127.0.0.1',
        'successful' => true,
        'created_at_diff' => $login->created_at->diffForHumans(),
    ]);
});

it('builds browser session payloads from the raw session rows', function (): void {
    $sessions = BrowserSessionData::collect([[
        'id' => 'current-session',
        'device' => 'Chrome on macOS',
        'ip_address' => null,
        'last_active_diff' => '1 second ago',
        'current' => true,
    ]]);

    expect($sessions)->toHaveCount(1)
        ->and($sessions[0]->id)->toBe('current-session')
        ->and($sessions[0]->ip_address)->toBeNull()
        ->and($sessions[0]->current)->toBeTrue();
});

it('builds the passkey payload with relative timestamps', function (): void {
    $user = User::factory()->create();

    $passkey = new Passkey([
        'name' => 'MacBook',
        'credential_id' => 'credential-id',
        'credential' => [],
    ]);
    $passkey->user_id = $user->id;
    $passkey->save();

    expect(PasskeyData::fromModel($passkey)->toArray())->toBe([
        'id' => $passkey->id,
        'name' => 'MacBook',
        'authenticator' => $passkey->authenticator,
        'created_at_diff' => $passkey->created_at?->diffForHumans(),
        'last_used_at_diff' => null,
    ]);
});
