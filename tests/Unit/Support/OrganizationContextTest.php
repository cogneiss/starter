<?php

declare(strict_types=1);

use App\Models\Organization;
use App\Support\OrganizationContext;

it('sets, reads and forgets the bound organization', function (): void {
    $organization = Organization::factory()->create();
    $context = new OrganizationContext;

    expect($context->has())->toBeFalse()
        ->and($context->get())->toBeNull()
        ->and($context->id())->toBeNull();

    $context->set($organization);

    expect($context->has())->toBeTrue()
        ->and($context->get()->is($organization))->toBeTrue()
        ->and($context->id())->toBe($organization->id);

    $context->forget();

    expect($context->has())->toBeFalse();
});

it('restores the previous organization after running as another', function (): void {
    $first = Organization::factory()->create();
    $second = Organization::factory()->create();

    $context = new OrganizationContext;
    $context->set($first);

    $result = $context->runAs($second, fn (): string => $context->id() ?? '');

    expect($result)->toBe($second->id)
        ->and($context->id())->toBe($first->id);
});

it('restores the previous organization when the callback throws', function (): void {
    $first = Organization::factory()->create();
    $second = Organization::factory()->create();

    $context = new OrganizationContext;
    $context->set($first);

    expect(fn (): mixed => $context->runAs($second, function (): never {
        throw new RuntimeException('boom');
    }))->toThrow(RuntimeException::class, 'boom');

    expect($context->id())->toBe($first->id);
});
