<?php

declare(strict_types=1);

use App\Exceptions\AiQuotaExceededException;
use App\Models\AiAuditLog;
use App\Models\AiCreditLedgerEntry;
use App\Models\OrganizationMembership;
use Tests\Fixtures\Ai\KernelFixtureAgent;

/**
 * @return array{0: App\Models\User, 1: App\Models\Organization}
 */
function quotaMember(): array
{
    $membership = OrganizationMembership::factory()->create();

    return [$membership->user, $membership->organization];
}

it('lets a member prompt while they are one request under the hourly limit', function (): void {
    [$user, $organization] = quotaMember();

    config()->set('ai.quotas.user_requests_per_hour', 60);

    AiAuditLog::factory()->count(59)->create([
        'organization_id' => $organization->id,
        'user_id' => $user->id,
    ]);

    KernelFixtureAgent::fake(['Under the limit.'])->preventStrayPrompts();

    $response = (new KernelFixtureAgent($user, $organization))->prompt('Say hello.');

    expect($response->text)->toBe('Under the limit.');

    KernelFixtureAgent::assertPrompted('Say hello.');
});

it('refuses the request that would exceed the hourly limit, without prompting the provider', function (): void {
    [$user, $organization] = quotaMember();

    config()->set('ai.quotas.user_requests_per_hour', 60);

    AiAuditLog::factory()->count(60)->create([
        'organization_id' => $organization->id,
        'user_id' => $user->id,
    ]);

    KernelFixtureAgent::fake(['Never sent.'])->preventStrayPrompts();

    expect(fn (): mixed => (new KernelFixtureAgent($user, $organization))->prompt('Say hello.'))
        ->toThrow(AiQuotaExceededException::class);

    $this->assertDatabaseCount('ai_audit_logs', 61);

    $this->assertDatabaseHas('ai_audit_logs', [
        'organization_id' => $organization->id,
        'user_id' => $user->id,
        'status' => 'blocked',
        'blocked_reason' => 'You have used all 60 AI requests allowed in an hour.',
        'cost_micros' => 0,
    ]);
});

it('stays refused once the member is past the hourly limit', function (): void {
    [$user, $organization] = quotaMember();

    config()->set('ai.quotas.user_requests_per_hour', 60);

    AiAuditLog::factory()->count(61)->create([
        'organization_id' => $organization->id,
        'user_id' => $user->id,
    ]);

    KernelFixtureAgent::fake(['Never sent.'])->preventStrayPrompts();

    expect(fn (): mixed => (new KernelFixtureAgent($user, $organization))->prompt('Say hello.'))
        ->toThrow(AiQuotaExceededException::class);

    $this->assertDatabaseCount('ai_audit_logs', 62);
});

it('does not count a refused request against the member', function (): void {
    [$user, $organization] = quotaMember();

    config()->set('ai.quotas.user_requests_per_hour', 60);

    AiAuditLog::factory()->count(59)->create([
        'organization_id' => $organization->id,
        'user_id' => $user->id,
    ]);

    AiAuditLog::factory()->blocked()->count(10)->create([
        'organization_id' => $organization->id,
        'user_id' => $user->id,
    ]);

    KernelFixtureAgent::fake(['Still allowed.'])->preventStrayPrompts();

    expect((new KernelFixtureAgent($user, $organization))->prompt('Say hello.')->text)
        ->toBe('Still allowed.');
});

it('counts another member of the organization against the daily organization limit only', function (): void {
    [$user, $organization] = quotaMember();

    $colleague = OrganizationMembership::factory()->create([
        'organization_id' => $organization->id,
    ])->user;

    config()->set('ai.quotas.user_requests_per_hour', 60);
    config()->set('ai.quotas.org_requests_per_day', 100);

    AiAuditLog::factory()->count(100)->create([
        'organization_id' => $organization->id,
        'user_id' => $colleague->id,
    ]);

    KernelFixtureAgent::fake(['Never sent.'])->preventStrayPrompts();

    expect(fn (): mixed => (new KernelFixtureAgent($user, $organization))->prompt('Say hello.'))
        ->toThrow(AiQuotaExceededException::class, 'This organization has used all 100 AI requests allowed in a day.');

    $this->assertDatabaseCount('ai_audit_logs', 101);
});

it('refuses once the organization has spent its monthly budget', function (): void {
    [$user, $organization] = quotaMember();

    config()->set('ai.quotas.org_budget_micros_per_month', 1_000);

    AiCreditLedgerEntry::factory()->create([
        'organization_id' => $organization->id,
        'delta_micros' => -1_000,
        'reason' => 'AI usage',
        'balance_micros_after' => -1_000,
    ]);

    KernelFixtureAgent::fake(['Never sent.'])->preventStrayPrompts();

    expect(fn (): mixed => (new KernelFixtureAgent($user, $organization))->prompt('Say hello.'))
        ->toThrow(AiQuotaExceededException::class, 'This organization has spent its AI budget for the month.');

    $this->assertDatabaseCount('ai_audit_logs', 1);
});

it('measures another organization spend against its own budget', function (): void {
    [$user, $organization] = quotaMember();
    [, $other] = quotaMember();

    config()->set('ai.quotas.org_budget_micros_per_month', 1_000);

    AiCreditLedgerEntry::factory()->create([
        'organization_id' => $other->id,
        'delta_micros' => -5_000,
        'reason' => 'AI usage',
        'balance_micros_after' => -5_000,
    ]);

    KernelFixtureAgent::fake(['Allowed.'])->preventStrayPrompts();

    expect((new KernelFixtureAgent($user, $organization))->prompt('Say hello.')->text)->toBe('Allowed.');
});

it('never reaches a provider for a user who is not a member of the organization', function (): void {
    $outsider = App\Models\User::factory()->create();
    [, $organization] = quotaMember();

    KernelFixtureAgent::fake(['Never sent.'])->preventStrayPrompts();

    expect(fn (): KernelFixtureAgent => new KernelFixtureAgent($outsider, $organization))
        ->toThrow(Illuminate\Auth\Access\AuthorizationException::class);

    KernelFixtureAgent::assertNeverPrompted();
});

it('answers a refused request with 429 and a machine readable error', function (): void {
    $response = (new AiQuotaExceededException('You have used all 60 AI requests allowed in an hour.'))
        ->render(request());

    expect($response->getStatusCode())->toBe(429)
        ->and($response->getData(true))->toBe([
            'error' => 'ai_quota_exceeded',
            'message' => 'You have used all 60 AI requests allowed in an hour.',
        ]);
});
