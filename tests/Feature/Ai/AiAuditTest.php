<?php

declare(strict_types=1);

use App\Ai\Middleware\EnforceQuota;
use App\Ai\Middleware\RecordAudit;
use App\Models\AiAuditLog;
use App\Models\AiCreditLedgerEntry;
use App\Models\Organization;
use App\Models\OrganizationMembership;
use App\Models\User;
use App\Support\AiPricing;
use App\Support\OrganizationContext;
use Laravel\Ai\Ai;
use Laravel\Ai\Prompts\AgentPrompt;
use Laravel\Ai\Responses\AgentResponse;
use Laravel\Ai\Responses\Data\Meta;
use Laravel\Ai\Responses\Data\Usage;
use Tests\Fixtures\Ai\KernelFixtureAgent;
use Tests\Fixtures\Ai\UnscopedFixtureAgent;

/**
 * @return array{0: User, 1: Organization}
 */
function auditMember(): array
{
    $membership = OrganizationMembership::factory()->create();

    return [$membership->user, $membership->organization];
}

it('writes one audit row for a prompt that reached a provider', function (): void {
    [$user, $organization] = auditMember();

    KernelFixtureAgent::fake(['Answered.'])->preventStrayPrompts();

    new KernelFixtureAgent($user, $organization)->prompt('Say hello.');

    resolve(OrganizationContext::class)->set($organization);

    $log = AiAuditLog::query()->sole();

    expect($log->organization_id)->toBe($organization->id)
        ->and($log->user_id)->toBe($user->id)
        ->and($log->agent)->toBe(KernelFixtureAgent::class)
        ->and($log->status->value)->toBe('ok')
        ->and($log->blocked_reason)->toBeNull()
        ->and($log->total_tokens)->toBe($log->prompt_tokens + $log->completion_tokens);
});

it('records an unpriced model as costing nothing rather than guessing a price', function (): void {
    [$user, $organization] = auditMember();

    config()->set('ai.pricing', []);

    KernelFixtureAgent::fake(['Answered.'])->preventStrayPrompts();

    new KernelFixtureAgent($user, $organization)->prompt('Say hello.');

    resolve(OrganizationContext::class)->set($organization);

    expect(AiAuditLog::query()->sole()->cost_micros)->toBe(0);

    $this->assertDatabaseCount('ai_credit_ledger', 0);
});

it('prices usage from the configured rate in whole micros', function (): void {
    config()->set('ai.pricing.anthropic.claude-haiku-4-5-20251001', [
        'input' => 1_000_000,
        'output' => 5_000_000,
    ]);

    $cost = AiPricing::costMicros(
        'anthropic',
        'claude-haiku-4-5-20251001',
        new Usage(promptTokens: 2_000_000, completionTokens: 1_000_000),
    );

    expect($cost)->toBe(7_000_000);
});

it('charges nothing for a model, or a provider, that has no configured price', function (): void {
    config()->set('ai.pricing.anthropic.claude-haiku-4-5-20251001', [
        'input' => 1_000_000,
        'output' => 5_000_000,
    ]);

    $usage = new Usage(promptTokens: 2_000_000, completionTokens: 1_000_000);

    expect(AiPricing::costMicros('anthropic', 'some-unknown-model', $usage))->toBe(0)
        ->and(AiPricing::costMicros('unknown-provider', 'claude-haiku-4-5-20251001', $usage))->toBe(0)
        ->and(AiPricing::costMicros(null, null, $usage))->toBe(0);
});

it('keeps one organization audit rows out of another', function (): void {
    [$user, $organization] = auditMember();
    [, $other] = auditMember();

    KernelFixtureAgent::fake(['Answered.'])->preventStrayPrompts();

    new KernelFixtureAgent($user, $organization)->prompt('Say hello.');

    resolve(OrganizationContext::class)->set($other);

    expect(AiAuditLog::query()->count())->toBe(0);

    resolve(OrganizationContext::class)->set($organization);

    expect(AiAuditLog::query()->count())->toBe(1);
});

it('meters a streamed response when the stream completes', function (): void {
    [$user, $organization] = auditMember();

    KernelFixtureAgent::fake(['Streamed answer.'])->preventStrayPrompts();

    $stream = new KernelFixtureAgent($user, $organization)->stream('Say hello.');

    resolve(OrganizationContext::class)->set($organization);

    expect(AiAuditLog::query()->count())->toBe(0);

    foreach ($stream as $event) {
        expect($event)->toBeObject();
    }

    expect(AiAuditLog::query()->sole()->status->value)->toBe('ok');
});

it('passes a response it cannot read through unmetered rather than charging a guess', function (): void {
    [$user, $organization] = auditMember();

    $agent = new KernelFixtureAgent($user, $organization);

    $prompt = new AgentPrompt(
        agent: $agent,
        prompt: 'Say hello.',
        attachments: [],
        provider: Ai::textProvider('anthropic'),
        model: 'claude-haiku-4-5-20251001',
    );

    $passed = resolve(RecordAudit::class)->handle($prompt, fn (): string => 'not a response');

    expect($passed)->toBe('not a response');

    resolve(OrganizationContext::class)->set($organization);

    expect(AiAuditLog::query()->count())->toBe(0);
});

it('rolls the audit row back when the charge for it fails', function (): void {
    [$user, $organization] = auditMember();

    config()->set('ai.pricing.anthropic.claude-haiku-4-5-20251001', [
        'input' => 1_000_000,
        'output' => 1_000_000,
    ]);

    AiCreditLedgerEntry::creating(function (): void {
        throw new RuntimeException('The ledger is unavailable.');
    });

    $agent = new KernelFixtureAgent($user, $organization);

    $prompt = new AgentPrompt(
        agent: $agent,
        prompt: 'Say hello.',
        attachments: [],
        provider: Ai::textProvider('anthropic'),
        model: 'claude-haiku-4-5-20251001',
    );

    $response = new AgentResponse(
        'invocation-1',
        'Answered.',
        new Usage(promptTokens: 1_000_000, completionTokens: 1_000_000),
        new Meta('anthropic', 'claude-haiku-4-5-20251001'),
    );

    expect(fn (): mixed => resolve(RecordAudit::class)->handle($prompt, fn (): AgentResponse => $response))
        ->toThrow(RuntimeException::class);

    resolve(OrganizationContext::class)->set($organization);

    // The whole point of the transaction: a charge that fails takes the row
    // that would have justified it with it.
    $this->assertDatabaseCount('ai_audit_logs', 0); // rolled back with the transaction
    $this->assertDatabaseCount('ai_credit_ledger', 0); // rolled back with the transaction
});

it('lets an agent that belongs to no organization through unmetered', function (): void {
    $prompt = new AgentPrompt(
        agent: new UnscopedFixtureAgent,
        prompt: 'Say hello.',
        attachments: [],
        provider: Ai::textProvider('anthropic'),
        model: 'claude-haiku-4-5-20251001',
    );

    expect(resolve(RecordAudit::class)->handle($prompt, fn (): string => 'through'))->toBe('through')
        ->and(resolve(EnforceQuota::class)->handle($prompt, fn (): string => 'through'))->toBe('through');

    $this->assertDatabaseCount('ai_audit_logs', 0);
});

it('reads the member an audit row belongs to', function (): void {
    [$user, $organization] = auditMember();

    $log = AiAuditLog::factory()->create([
        'organization_id' => $organization->id,
        'user_id' => $user->id,
    ]);

    expect($log->user->id)->toBe($user->id);
});

it('refuses to rewrite or delete an audit row', function (): void {
    [, $organization] = auditMember();

    $log = AiAuditLog::factory()->create(['organization_id' => $organization->id]);

    expect(fn (): bool => $log->update(['status' => 'failed']))->toThrow(LogicException::class)
        ->and(fn (): bool => $log->delete())->toThrow(LogicException::class);
});
