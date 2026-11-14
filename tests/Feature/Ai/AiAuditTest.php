<?php

declare(strict_types=1);

use App\Ai\Middleware\EnforceQuota;
use App\Ai\Middleware\RecordAudit;
use App\Enums\AiAuditStatus;
use App\Models\AiAuditLog;
use App\Models\AiCreditLedgerEntry;
use App\Models\Organization;
use App\Models\OrganizationMembership;
use App\Models\User;
use App\Support\AiPricing;
use App\Support\OrganizationContext;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Gateway\StepResponse;
use Laravel\Ai\Gateway\StepResult;
use Laravel\Ai\Gateway\TextGenerationOptions;
use Laravel\Ai\Messages\UserMessage;
use Laravel\Ai\PendingStep;
use Laravel\Ai\Responses\Data\FinishReason;
use Laravel\Ai\Responses\Data\Meta;
use Laravel\Ai\Responses\Data\TextUsage;
use Laravel\Ai\Responses\Data\ToolCall;
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

function auditStep(Agent $agent, ?string $invocationId = null): PendingStep
{
    return new PendingStep(
        number: 0,
        isFinalStep: false,
        provider: 'anthropic',
        model: 'claude-haiku-5-5',
        instructions: null,
        messages: [new UserMessage('Say hello.')],
        tools: [],
        schema: null,
        options: new TextGenerationOptions(agent: $agent),
        invocationId: $invocationId,
    );
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

it('bills an unpriced model at the fallback ceiling rather than at zero', function (): void {
    [$user, $organization] = auditMember();

    config()->set('ai.pricing', []);

    KernelFixtureAgent::fake(['Answered.'])->preventStrayPrompts();

    new KernelFixtureAgent($user, $organization)->prompt('Say hello.');

    resolve(OrganizationContext::class)->set($organization);

    $log = AiAuditLog::query()->sole();

    expect($log->cost_micros)->toBe(
        intdiv($log->prompt_tokens * 4_000_000, 1_000_000) + intdiv($log->completion_tokens * 20_000_000, 1_000_000),
    );
});

it('prices usage from the configured rate in whole micros', function (): void {
    config()->set('ai.pricing.anthropic.claude-haiku-5-5', [
        'input' => 1_000_000,
        'output' => 5_000_000,
    ]);

    $cost = AiPricing::costMicros(
        'anthropic',
        'claude-haiku-5-5',
        new Usage(inputTokens: 2_000_000, outputTokens: 1_000_000),
    );

    expect($cost)->toBe(7_000_000);
});

it('charges the fallback ceiling for a model, or a provider, that has no configured price', function (): void {
    config()->set('ai.pricing.anthropic.claude-haiku-5-5', [
        'input' => 1_000_000,
        'output' => 5_000_000,
    ]);

    $usage = new Usage(inputTokens: 2_000_000, outputTokens: 1_000_000);

    $ceiling = 2 * 4_000_000 + 20_000_000;

    expect(AiPricing::costMicros('anthropic', 'some-unknown-model', $usage))->toBe($ceiling)
        ->and(AiPricing::costMicros('unknown-provider', 'claude-haiku-5-5', $usage))->toBe($ceiling)
        ->and(AiPricing::costMicros(null, null, $usage))->toBe($ceiling);
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

    $step = auditStep($agent);

    $passed = resolve(RecordAudit::class)->handle($step, fn (): string => 'not a response');

    expect($passed)->toBe('not a response');

    resolve(OrganizationContext::class)->set($organization);

    expect(AiAuditLog::query()->count())->toBe(0);
});

it('rolls the audit row back when the charge for it fails', function (): void {
    [$user, $organization] = auditMember();

    config()->set('ai.pricing.anthropic.claude-haiku-5-5', [
        'input' => 1_000_000,
        'output' => 1_000_000,
    ]);

    AiCreditLedgerEntry::creating(function (): void {
        throw new RuntimeException('The ledger is unavailable.');
    });

    $agent = new KernelFixtureAgent($user, $organization);

    $step = auditStep($agent);

    $response = new StepResponse(
        'Answered.',
        [],
        FinishReason::Stop,
        new TextUsage(inputTokens: 1_000_000, outputTokens: 1_000_000),
        new Meta('anthropic', 'claude-haiku-5-5'),
    );

    expect(fn (): ?StepResponse => resolve(RecordAudit::class)->handle($step, fn (): StepResult => new StepResult($response))->response())
        ->toThrow(RuntimeException::class);

    resolve(OrganizationContext::class)->set($organization);

    // The whole point of the transaction: a charge that fails takes the row
    // that would have justified it with it.
    $this->assertDatabaseCount('ai_audit_logs', 0); // rolled back with the transaction
    $this->assertDatabaseCount('ai_credit_ledger', 0); // rolled back with the transaction
});

it('lets an agent that belongs to no organization through unmetered', function (): void {
    $step = auditStep(new UnscopedFixtureAgent);

    expect(resolve(RecordAudit::class)->handle($step, fn (): string => 'through'))->toBe('through')
        ->and(resolve(EnforceQuota::class)->handle($step, fn (): string => 'through'))->toBe('through');

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

it('groups every step of one invocation under the SDK invocation id', function (): void {
    [$user, $organization] = auditMember();

    $agent = new KernelFixtureAgent($user, $organization);
    $response = new StepResponse('Done.', [], FinishReason::Stop, new TextUsage(1, 1), new Meta('anthropic', 'claude-haiku-5-5'));

    foreach ([0, 1] as $number) {
        resolve(RecordAudit::class)->handle(auditStep($agent, 'run-1'), fn (): StepResult => new StepResult($response))->response();
    }

    resolve(OrganizationContext::class)->set($organization);

    expect(AiAuditLog::query()->pluck('run_id')->all())->toBe(['run-1', 'run-1']);
});

it('records tool arguments redacted by key name and capped in size', function (): void {
    [$user, $organization] = auditMember();

    $agent = new KernelFixtureAgent($user, $organization);
    $call = new ToolCall('t1', 'lookup', [
        'query' => str_repeat('a', 500),
        'api_key' => 'sk-live-should-never-be-stored',
        'nested' => ['token' => 'abc', 'id' => 7],
    ]);
    $response = new StepResponse('', [$call], FinishReason::ToolCalls, new TextUsage(1, 1), new Meta('anthropic', 'claude-haiku-5-5'));

    resolve(RecordAudit::class)->handle(auditStep($agent, 'run-2'), fn (): StepResult => new StepResult($response))->response();

    resolve(OrganizationContext::class)->set($organization);

    $arguments = AiAuditLog::query()->sole()->tool_arguments[0]['arguments'];

    expect($arguments['api_key'])->toBe('[redacted]')
        ->and($arguments['nested'])->toBe(['token' => '[redacted]', 'id' => 7])
        ->and(mb_strlen($arguments['query']))->toBeLessThanOrEqual(200)
        ->and(json_encode($arguments))->not->toContain('sk-live');
});

it('keeps only the tool names when the arguments are too large to store', function (): void {
    [$user, $organization] = auditMember();

    $agent = new KernelFixtureAgent($user, $organization);
    $calls = array_map(fn (int $number): ToolCall => new ToolCall('t'.$number, 'lookup', ['query' => str_repeat('a', 500)]), range(1, 30));
    $response = new StepResponse('', $calls, FinishReason::ToolCalls, new TextUsage(1, 1), new Meta('anthropic', 'claude-haiku-5-5'));

    resolve(RecordAudit::class)->handle(auditStep($agent, 'run-4'), fn (): StepResult => new StepResult($response))->response();

    resolve(OrganizationContext::class)->set($organization);

    expect(AiAuditLog::query()->sole()->tool_arguments)
        ->toHaveCount(30)
        ->each->toBe(['name' => 'lookup', 'arguments' => ['truncated' => true]]);
});

it('records an error row and rethrows when the provider call throws', function (): void {
    [$user, $organization] = auditMember();

    $agent = new KernelFixtureAgent($user, $organization);

    expect(fn (): mixed => resolve(RecordAudit::class)->handle(auditStep($agent, 'run-3'), fn (): never => throw new RuntimeException('Provider down.')))
        ->toThrow(RuntimeException::class, 'Provider down.');

    resolve(OrganizationContext::class)->set($organization);

    $log = AiAuditLog::query()->sole();

    expect($log->status)->toBe(AiAuditStatus::Error)
        ->and($log->run_id)->toBe('run-3')
        ->and($log->error)->toContain('Provider down.')
        ->and($log->cost_micros)->toBe(0);
});

it('rethrows the provider failure when the error row cannot be written either', function (): void {
    [$user, $organization] = auditMember();

    AiAuditLog::creating(function (): void {
        throw new RuntimeException('The audit table is unavailable.');
    });

    $agent = new KernelFixtureAgent($user, $organization);

    expect(fn (): mixed => resolve(RecordAudit::class)->handle(auditStep($agent, 'run-5'), fn (): never => throw new RuntimeException('Provider down.')))
        ->toThrow(RuntimeException::class, 'Provider down.');

    $this->assertDatabaseCount('ai_audit_logs', 0);
});
