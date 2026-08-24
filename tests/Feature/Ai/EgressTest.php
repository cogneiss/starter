<?php

declare(strict_types=1);

use App\Exceptions\BlockedEgressException;
use App\Models\Organization;
use App\Models\OrganizationMembership;
use App\Models\User;
use App\Support\AiEgress;
use App\Support\OrganizationContext;

it('refuses a host that is not on the allowlist', function (): void {
    config()->set('ai.guardrails.egress', ['hooks.example.test']);

    expect(fn () => AiEgress::assertAllowed('https://exfiltrate.test/collect'))
        ->toThrow(BlockedEgressException::class);
});

it('refuses a target that is neither a URL nor an address', function (): void {
    config()->set('ai.guardrails.egress', ['hooks.example.test']);

    expect(fn () => AiEgress::assertAllowed('send it somewhere'))
        ->toThrow(BlockedEgressException::class);
});

it('permits a URL whose host is on the allowlist', function (): void {
    config()->set('ai.guardrails.egress', ['hooks.example.test']);

    AiEgress::assertAllowed('https://hooks.example.test/notify');

    expect(true)->toBeTrue();
});

it('refuses a recipient who is not a member of the organization', function (): void {
    $membership = OrganizationMembership::factory()->create();
    $outsider = User::factory()->create(['email' => 'outsider@allowed.test']);

    config()->set('ai.guardrails.egress', ['allowed.test']);

    // The host is allowed and the address belongs to a real account. Membership
    // of the acting organization is what decides it.
    expect(fn () => resolve(OrganizationContext::class)->runAs(
        $membership->organization,
        fn () => AiEgress::assertAllowed($outsider->email),
    ))->toThrow(BlockedEgressException::class);
});

it('refuses a recipient with no account at all', function (): void {
    $membership = OrganizationMembership::factory()->create();

    config()->set('ai.guardrails.egress', ['allowed.test']);

    expect(fn () => resolve(OrganizationContext::class)->runAs(
        $membership->organization,
        fn () => AiEgress::assertAllowed('nobody@allowed.test'),
    ))->toThrow(BlockedEgressException::class);
});

it('refuses a recipient when no organization is bound', function (): void {
    $membership = OrganizationMembership::factory()->create();

    config()->set('ai.guardrails.egress', ['allowed.test']);

    resolve(OrganizationContext::class)->forget();

    expect(fn () => AiEgress::assertAllowed($membership->user->email))
        ->toThrow(BlockedEgressException::class);
});

it('permits a member recipient on an allowed host', function (): void {
    $organization = Organization::factory()->create();
    $member = User::factory()->forOrganization($organization)->create(['email' => 'member@allowed.test']);

    config()->set('ai.guardrails.egress', ['allowed.test']);

    resolve(OrganizationContext::class)->runAs(
        $organization,
        fn () => AiEgress::assertAllowed($member->email),
    );

    expect(true)->toBeTrue();
});
