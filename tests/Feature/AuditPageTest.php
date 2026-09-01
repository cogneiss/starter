<?php

declare(strict_types=1);

use App\Models\Activity;
use App\Models\ApiToken;
use App\Models\Organization;
use App\Models\SavedSearch;
use App\Models\User;
use Inertia\Testing\AssertableInertia;

/**
 * The audit page is the list kit reading the activity table: the same scoped
 * query serves the screen and the CSV, so what one shows is exactly what the
 * other writes.
 */
beforeEach(function (): void {
    $this->organization = Organization::factory()->create();
    $this->member = User::factory()->forOrganization($this->organization)->create();

    $theirs = Organization::factory()->create();

    $this->foreign = Activity::factory()->create([
        'organization_id' => $theirs->id,
        'description' => 'a-foreign-org-entry',
    ]);
});

/**
 * The user factory's onboarding side effects are themselves audited; clear
 * our organization's ledger so each test asserts against its own fixtures.
 */
function wipeOwnAudit(string $organizationId): void
{
    Activity::withoutOrganizationScope()->where('organization_id', $organizationId)->delete();
}

it('renders the page and never another organization’s entries', function (): void {
    wipeOwnAudit($this->organization->id);

    Activity::factory()->create([
        'organization_id' => $this->organization->id,
        'description' => 'our-own-entry',
    ]);

    $this->actingAs($this->member)
        ->get(route('audit-log.index'))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
            ->component('audit/index')
            ->where('entries.total', 1)
            ->where('entries.rows.0.description', 'our-own-entry'),
        );
});

it('filters by causer, event, subject type and date range', function (): void {
    $other = User::factory()->forOrganization($this->organization)->create();

    wipeOwnAudit($this->organization->id);

    Activity::factory()->create([
        'organization_id' => $this->organization->id,
        'description' => 'token-created-by-member',
        'event' => 'created',
        'subject_type' => ApiToken::class,
        'causer_type' => $this->member->getMorphClass(),
        'causer_id' => $this->member->id,
        'created_at' => '2026-08-01 12:00:00',
    ]);
    Activity::factory()->create([
        'organization_id' => $this->organization->id,
        'description' => 'search-deleted-by-other',
        'event' => 'deleted',
        'subject_type' => SavedSearch::class,
        'causer_type' => $other->getMorphClass(),
        'causer_id' => $other->id,
        'created_at' => '2026-08-20 12:00:00',
    ]);

    $narrowing = [
        'causer' => ['causer' => [$this->member->id]],
        'event' => ['event' => ['created']],
        'subject' => ['subject' => [ApiToken::class]],
        'when' => ['when' => ['from' => '2026-07-25', 'to' => '2026-08-10']],
    ];

    foreach ($narrowing as $filter) {
        $this->actingAs($this->member)
            ->get(route('audit-log.index').'?'.http_build_query(['f' => $filter]))
            ->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
                ->where('entries.total', 1)
                ->where('entries.rows.0.description', 'token-created-by-member'),
            );
    }
});

it('exports the same scoped, filtered query as CSV', function (): void {
    wipeOwnAudit($this->organization->id);

    Activity::factory()->create([
        'organization_id' => $this->organization->id,
        'description' => 'an-entry-that-matches',
        'event' => 'created',
    ]);
    Activity::factory()->create([
        'organization_id' => $this->organization->id,
        'description' => 'an-entry-filtered-out',
        'event' => 'deleted',
    ]);

    $csv = $this->actingAs($this->member)
        ->get(route('audit-log.index').'?'.http_build_query(['f' => ['event' => ['created']]]), ['Accept' => 'text/csv'])
        ->streamedContent();

    expect($csv)->toContain('an-entry-that-matches');

    $this->assertStringNotContainsString('an-entry-filtered-out', $csv);
    $this->assertStringNotContainsString('a-foreign-org-entry', $csv);
});
