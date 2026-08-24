<?php

declare(strict_types=1);

use App\Models\AiCreditLedgerEntry;
use App\Models\Organization;
use App\Models\User;
use App\Support\OrganizationContext;
use Illuminate\Support\Facades\Gate;

it('lets an owner read the ledger and grant credit', function (): void {
    $organization = Organization::factory()->create();
    $user = User::factory()->forOrganization($organization)->create();
    $entry = AiCreditLedgerEntry::factory()->create(['organization_id' => $organization->id]);

    resolve(OrganizationContext::class)->runAs($organization, function () use ($user, $entry): void {
        expect(Gate::forUser($user)->allows('viewAny', AiCreditLedgerEntry::class))->toBeTrue()
            ->and(Gate::forUser($user)->allows('view', $entry))->toBeTrue()
            ->and(Gate::forUser($user)->allows('create', AiCreditLedgerEntry::class))->toBeTrue();
    });
});

it('lets a member read the ledger but never write to it', function (): void {
    $organization = Organization::factory()->create();
    $user = User::factory()->forOrganization($organization, 'Member')->create();
    $entry = AiCreditLedgerEntry::factory()->create(['organization_id' => $organization->id]);

    resolve(OrganizationContext::class)->runAs($organization, function () use ($user, $entry): void {
        expect(Gate::forUser($user)->allows('view', $entry))->toBeTrue()
            ->and(Gate::forUser($user)->allows('create', AiCreditLedgerEntry::class))->toBeFalse();
    });
});

it('refuses a ledger entry that belongs to another organization', function (): void {
    $organization = Organization::factory()->create();
    $user = User::factory()->forOrganization($organization)->create();
    $entry = AiCreditLedgerEntry::factory()->create();

    resolve(OrganizationContext::class)->runAs($organization, function () use ($user, $entry): void {
        expect(Gate::forUser($user)->allows('view', $entry))->toBeFalse();
    });
});

it('refuses a user with no organization bound', function (): void {
    $organization = Organization::factory()->create();
    $user = User::factory()->forOrganization($organization)->create();

    expect(Gate::forUser($user)->allows('viewAny', AiCreditLedgerEntry::class))->toBeFalse()
        ->and(Gate::forUser($user)->allows('create', AiCreditLedgerEntry::class))->toBeFalse();
});
