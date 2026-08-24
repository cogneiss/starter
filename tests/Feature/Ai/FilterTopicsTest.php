<?php

declare(strict_types=1);

use App\Exceptions\BlockedTopicException;
use App\Models\OrganizationMembership;
use Tests\Fixtures\Ai\KernelFixtureAgent;

it('refuses a prompt about a denied topic before it reaches a provider', function (): void {
    $membership = OrganizationMembership::factory()->create();

    config()->set('ai.guardrails.denied_topics', ['medical advice']);

    KernelFixtureAgent::fake(['Never sent.'])->preventStrayPrompts();

    expect(fn (): mixed => (new KernelFixtureAgent($membership->user, $membership->organization))
        ->prompt('Give me Medical Advice about this rash.'))
        ->toThrow(BlockedTopicException::class);

    KernelFixtureAgent::assertPromptedTimes(1);

    $this->assertDatabaseCount('ai_audit_logs', 0);
});

it('passes the prompt through when the denied topic list is empty', function (): void {
    $membership = OrganizationMembership::factory()->create();

    config()->set('ai.guardrails.denied_topics', []);

    KernelFixtureAgent::fake(['Answered.'])->preventStrayPrompts();

    $response = (new KernelFixtureAgent($membership->user, $membership->organization))
        ->prompt('Give me medical advice about this rash.');

    expect($response->text)->toBe('Answered.');
});
