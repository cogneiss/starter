<?php

declare(strict_types=1);

use App\Actions\DebitAiCredits;
use App\Actions\GrantAiCredits;
use App\Models\AiCreditLedgerEntry;
use App\Models\Organization;
use App\Models\User;
use App\Support\OrganizationContext;
use Illuminate\Auth\Access\AuthorizationException;

it('carries the running balance forward on every entry', function (): void {
    $organization = Organization::factory()->create();

    resolve(GrantAiCredits::class)->handle($organization, 10_000_000, 'Signup grant');
    resolve(DebitAiCredits::class)->handle($organization, 2_500_000, 'AI usage');
    resolve(GrantAiCredits::class)->handle($organization, 500_000, 'Support credit');

    resolve(OrganizationContext::class)->set($organization);

    expect(AiCreditLedgerEntry::balanceMicros())->toBe(8_000_000)
        ->and(AiCreditLedgerEntry::query()->orderByDesc('balance_micros_after')->first()?->balance_micros_after)
        ->toBe(10_000_000);
});

it('records a debit as a negative movement', function (): void {
    $organization = Organization::factory()->create();

    $entry = resolve(DebitAiCredits::class)->handle($organization, 1_200, 'AI usage');

    expect($entry->delta_micros)->toBe(-1_200)
        ->and($entry->reason)->toBe('AI usage')
        ->and($entry->balance_micros_after)->toBe(-1_200);
});

it('refuses a grant or a debit that is not a positive amount', function (): void {
    $organization = Organization::factory()->create();

    expect(fn (): AiCreditLedgerEntry => resolve(GrantAiCredits::class)->handle($organization, 0, 'Nothing'))
        ->toThrow(InvalidArgumentException::class)
        ->and(fn (): AiCreditLedgerEntry => resolve(DebitAiCredits::class)->handle($organization, -5, 'Nothing'))
        ->toThrow(InvalidArgumentException::class);
});

it('keeps one organization balance out of another', function (): void {
    $organization = Organization::factory()->create();
    $other = Organization::factory()->create();

    resolve(GrantAiCredits::class)->handle($organization, 4_000, 'Signup grant');
    resolve(GrantAiCredits::class)->handle($other, 9_000, 'Signup grant');

    resolve(OrganizationContext::class)->set($organization);
    expect(AiCreditLedgerEntry::balanceMicros())->toBe(4_000);

    resolve(OrganizationContext::class)->set($other);
    expect(AiCreditLedgerEntry::balanceMicros())->toBe(9_000);
});

it('refuses to rewrite or delete a ledger entry', function (): void {
    $organization = Organization::factory()->create();

    $entry = resolve(GrantAiCredits::class)->handle($organization, 4_000, 'Signup grant');

    expect(fn (): bool => $entry->update(['delta_micros' => 1]))->toThrow(LogicException::class)
        ->and(fn (): bool => $entry->delete())->toThrow(LogicException::class);
});

it('refuses a grant from someone without permission to grant credit', function (): void {
    $organization = Organization::factory()->create();
    $member = User::factory()->forOrganization($organization, 'Member')->create();

    expect(fn (): AiCreditLedgerEntry => resolve(GrantAiCredits::class)->handle($organization, 5_000, 'Support credit', $member))->toThrow(AuthorizationException::class);

    $this->assertDatabaseCount('ai_credit_ledger', 0);
});

it('refuses a debit from someone without permission to move credit', function (): void {
    $organization = Organization::factory()->create();
    $member = User::factory()->forOrganization($organization, 'Member')->create();

    expect(fn (): AiCreditLedgerEntry => resolve(DebitAiCredits::class)->handle($organization, 5_000, 'AI usage', null, $member))->toThrow(AuthorizationException::class);

    $this->assertDatabaseCount('ai_credit_ledger', 0);
});

it('lets someone with permission grant credit', function (): void {
    $organization = Organization::factory()->create();
    $owner = User::factory()->forOrganization($organization)->create();

    resolve(GrantAiCredits::class)->handle($organization, 5_000, 'Support credit', $owner);

    $this->assertDatabaseHas('ai_credit_ledger', [
        'organization_id' => $organization->id,
        'delta_micros' => 5_000,
        'reason' => 'Support credit',
    ]);
});

it('charges the organization on the system path, where there is no actor', function (): void {
    $organization = Organization::factory()->create();

    resolve(DebitAiCredits::class)->handle($organization, 750, 'AI usage', null, actor: null);

    $this->assertDatabaseHas('ai_credit_ledger', [
        'organization_id' => $organization->id,
        'delta_micros' => -750,
        'reason' => 'AI usage',
    ]);
});

it('links a debit to the audit row it paid for', function (): void {
    $organization = Organization::factory()->create();

    $log = App\Models\AiAuditLog::factory()->create(['organization_id' => $organization->id]);

    $entry = resolve(DebitAiCredits::class)->handle($organization, 200, 'AI usage', $log);

    expect($entry->reference_type)->toBe($log->getMorphClass())
        ->and($entry->reference_id)->toBe($log->id);
});
