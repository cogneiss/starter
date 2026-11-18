<?php

declare(strict_types=1);

use App\Events\OrganizationCreated;
use App\Models\AiCreditLedgerEntry;
use App\Models\Organization;
use App\Models\User;
use App\Support\OrganizationContext;

function ledgerFor(Organization $organization): array
{
    return resolve(OrganizationContext::class)->runAs(
        $organization,
        fn (): array => AiCreditLedgerEntry::query()->get(['delta_micros', 'reason', 'balance_micros_after'])->toArray(),
    );
}

it('posts the starter credit when an organization is created', function (): void {
    config()->set('ai.credits.starter_micros', 5_000_000);

    $organization = Organization::factory()->create();

    event(new OrganizationCreated($organization, User::factory()->create()));

    expect(ledgerFor($organization))->toBe([
        ['delta_micros' => 5_000_000, 'reason' => 'Starter credit', 'balance_micros_after' => 5_000_000],
    ]);
});

it('posts nothing when the starter credit is zero', function (): void {
    config()->set('ai.credits.starter_micros', 0);

    $organization = Organization::factory()->create();

    event(new OrganizationCreated($organization, User::factory()->create()));

    expect(ledgerFor($organization))->toBe([]);
});
