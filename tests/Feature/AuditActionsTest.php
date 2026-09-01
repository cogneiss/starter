<?php

declare(strict_types=1);

use App\Actions\UpdateOrganizationMembershipRole;
use App\Models\Activity;
use App\Models\FeatureOverride;
use App\Models\ImportBatch;
use App\Models\Organization;
use App\Models\OrganizationInvitation;
use App\Models\OrganizationMembership;
use App\Models\User;
use App\Support\OrganizationContext;

/**
 * The named administrative acts of the plan, each leaving exactly one line in
 * the ledger — not zero, and not one per implementation detail.
 */
beforeEach(function (): void {
    $this->organization = Organization::factory()->create();
    $this->owner = User::factory()->forOrganization($this->organization)->create();
});

function auditEntriesFor(object $subject, string $event): int
{
    return Activity::withoutOrganizationScope()
        ->where('subject_type', $subject->getMorphClass())
        ->where('subject_id', $subject->getKey())
        ->where('event', $event)
        ->count();
}

it('writes exactly one entry for a role change', function (): void {
    $member = User::factory()->forOrganization($this->organization, 'Member')->create();

    $membership = resolve(OrganizationContext::class)->runAs(
        $this->organization,
        fn (): OrganizationMembership => OrganizationMembership::query()
            ->where('organization_id', $this->organization->id)
            ->where('user_id', $member->id)
            ->firstOrFail(),
    );

    $this->actingAs($this->owner);

    resolve(UpdateOrganizationMembershipRole::class)->handle($membership, 'Owner');

    expect(auditEntriesFor($membership, 'role_changed'))->toBe(1);

    $entry = Activity::withoutOrganizationScope()
        ->where('subject_id', $membership->id)
        ->where('event', 'role_changed')
        ->sole();

    expect($entry->organization_id)->toBe($this->organization->id)
        ->and($entry->causer_id)->toBe($this->owner->id);
});

it('writes exactly one entry for an invitation create', function (): void {
    $invitation = resolve(OrganizationContext::class)->runAs(
        $this->organization,
        fn (): OrganizationInvitation => OrganizationInvitation::factory()
            ->create(['organization_id' => $this->organization->id]),
    );

    expect(auditEntriesFor($invitation, 'created'))->toBe(1);
});

it('writes exactly one entry for a feature override create', function (): void {
    $override = FeatureOverride::factory()->create(['organization_id' => $this->organization->id]);

    expect(auditEntriesFor($override, 'created'))->toBe(1);

    // FeatureOverride is deliberately unscoped, so the stamp comes from the
    // row's own organization, not a bound context.
    expect(Activity::withoutOrganizationScope()
        ->where('subject_id', $override->id)
        ->sole()
        ->organization_id)->toBe($this->organization->id);
});

it('writes exactly one entry for an import run', function (): void {
    $batch = resolve(OrganizationContext::class)->runAs(
        $this->organization,
        fn (): ImportBatch => ImportBatch::factory()->create(['organization_id' => $this->organization->id]),
    );

    expect(auditEntriesFor($batch, 'created'))->toBe(1);
});

it('writes exactly one entry for an export run', function (): void {
    $this->actingAs($this->owner)
        ->get(route('organization-member.edit'), ['Accept' => 'text/csv'])
        ->assertOk();

    $entries = Activity::withoutOrganizationScope()->where('event', 'exported')->get();

    expect($entries)->toHaveCount(1)
        ->and($entries->sole()->organization_id)->toBe($this->organization->id)
        ->and($entries->sole()->causer_id)->toBe($this->owner->id)
        ->and($entries->sole()->description)->toBe('exported organization-members');
});
