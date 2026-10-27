<?php

declare(strict_types=1);

use App\Ai\Middleware\FilterTopics;
use App\Exceptions\BlockedTopicException;
use App\Models\OrganizationMembership;
use Laravel\Ai\Gateway\TextGenerationOptions;
use Laravel\Ai\Messages\UserMessage;
use Laravel\Ai\PendingStep;
use Tests\Fixtures\Ai\KernelFixtureAgent;
use Tests\Fixtures\Ai\UnscopedFixtureAgent;

it('refuses a prompt about a denied topic before it reaches a provider', function (): void {
    $membership = OrganizationMembership::factory()->create();

    config()->set('ai.guardrails.denied_topics', ['medical advice']);

    KernelFixtureAgent::fake(['Never sent.'])->preventStrayPrompts();

    expect(fn (): mixed => new KernelFixtureAgent($membership->user, $membership->organization)
        ->prompt('Give me Medical Advice about this rash.'))
        ->toThrow(BlockedTopicException::class);

    KernelFixtureAgent::assertPromptedTimes(1);

    $this->assertDatabaseCount('ai_audit_logs', 0);
});

it('passes the prompt through when the denied topic list is empty', function (): void {
    $membership = OrganizationMembership::factory()->create();

    config()->set('ai.guardrails.denied_topics', []);

    KernelFixtureAgent::fake(['Answered.'])->preventStrayPrompts();

    $response = new KernelFixtureAgent($membership->user, $membership->organization)
        ->prompt('Give me medical advice about this rash.');

    expect($response->text)->toBe('Answered.');
});

it('checks only the first step, so a tool round-trip is not refused again', function (): void {
    config()->set('ai.guardrails.denied_topics', ['medical advice']);

    $step = new PendingStep(
        number: 1,
        isFinalStep: false,
        provider: 'anthropic',
        model: 'claude-haiku-4-5-20251001',
        instructions: null,
        messages: [new UserMessage('Give me medical advice about this rash.')],
        tools: [],
        schema: null,
        options: new TextGenerationOptions(agent: new UnscopedFixtureAgent),
    );

    expect(resolve(FilterTopics::class)->handle($step, fn (): string => 'through'))->toBe('through');
});
