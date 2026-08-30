<?php

declare(strict_types=1);

use App\Models\ImportBatch;
use App\Models\Organization;
use App\Models\OrganizationInvitation;
use App\Models\OrganizationMembership;
use App\Models\SavedSearch;
use App\Models\TempUpload;
use App\Models\User;
use App\Notifications\OrganizationInvitationNotification;
use App\Support\OrganizationContext;

/**
 * One case per surface the UX layer added, each asking the same question: what
 * happens when a request carries an id that belongs to another organization?
 *
 * The answer is always a 404. The organization is a where clause on the query
 * the database runs, so the row is never selected; a 403 would mean the row was
 * fetched, read and then refused, which also confirms to a stranger that the id
 * is real. The export case is the one that matters most — a screen that loses
 * its scope shows a page of someone else's rows, an export writes the table.
 */

/**
 * The stranger's membership row, as their own organization holds it.
 */
function strangerMembership(User $stranger, Organization $theirs): OrganizationMembership
{
    return resolve(OrganizationContext::class)->runAs(
        $theirs,
        fn (): OrganizationMembership => OrganizationMembership::query()
            ->where('organization_id', $theirs->id)
            ->where('user_id', $stranger->id)
            ->firstOrFail(),
    );
}

it("does not find another organization's row from the list", function (): void {
    $ours = Organization::factory()->create();
    $owner = User::factory()->forOrganization($ours)->create();

    $theirs = Organization::factory()->create();
    $stranger = User::factory()->forOrganization($theirs)->create();

    $membership = strangerMembership($stranger, $theirs);

    $this->actingAs($owner)
        ->get(route('organization-member.edit'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->where('members.total', 1));

    $this->actingAs($owner)
        ->patch(route('organization-member.update', $membership->id), ['status' => 'suspended'])
        ->assertNotFound();
});

it("does not widen the list when a filter names another organization's row", function (): void {
    $ours = Organization::factory()->create();
    $owner = User::factory()->forOrganization($ours)->create();

    $theirs = Organization::factory()->create();
    $stranger = User::factory()->forOrganization($theirs)->create();

    $membership = strangerMembership($stranger, $theirs);

    // A search term is the narrowest thing a person can say about a list. It
    // cannot reach past the scope the query already applied, so naming the other
    // tenant's own address returns nothing at all.
    $this->actingAs($owner)
        ->get(route('organization-member.edit', ['q' => $stranger->email]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->where('members.total', 0));

    $this->actingAs($owner)
        ->get(route('organization-member.edit', ['q' => $stranger->email, 'peek' => $membership->id]))
        ->assertNotFound();
});

it('writes no other organization row into a csv export', function (): void {
    $ours = Organization::factory()->create();
    $owner = User::factory()->forOrganization($ours)->create();

    $theirs = Organization::factory()->create();
    $stranger = User::factory()->forOrganization($theirs)->create();

    $membership = strangerMembership($stranger, $theirs);

    $csv = $this->actingAs($owner)
        ->get(route('organization-member.edit'), ['Accept' => 'text/csv'])
        ->streamedContent();

    expect($csv)->toContain($owner->email);

    $this->assertStringNotContainsString($stranger->email, $csv);

    // The bytes above are the whole answer for rows the export could write. The
    // id itself is unreachable too, so an export has nothing to name.
    $this->actingAs($owner)
        ->delete(route('organization-member.destroy', $membership->id))
        ->assertNotFound();
});

it("does not open another organization's saved search", function (): void {
    $ours = Organization::factory()->create();
    $owner = User::factory()->forOrganization($ours)->create();

    $theirs = Organization::factory()->create();
    $stranger = User::factory()->forOrganization($theirs)->create();

    $search = resolve(OrganizationContext::class)->runAs(
        $theirs,
        fn (): SavedSearch => SavedSearch::factory()->create([
            'organization_id' => $theirs->id,
            'user_id' => $stranger->id,
        ]),
    );

    $this->actingAs($owner)
        ->get(route('saved-search.show', $search->id))
        ->assertNotFound();
});

it("does not mark another organization's notification read", function (): void {
    $ours = Organization::factory()->create();
    $owner = User::factory()->forOrganization($ours)->create();

    $theirs = Organization::factory()->create();
    $stranger = User::factory()->forOrganization($theirs)->create();

    resolve(OrganizationContext::class)->runAs($theirs, function () use ($stranger, $theirs): void {
        $invitation = OrganizationInvitation::factory()->create(['organization_id' => $theirs->id]);

        $stranger->notify(new OrganizationInvitationNotification($invitation, 'token'));
    });

    $notification = $stranger->notifications()->firstOrFail();

    $this->actingAs($owner)
        ->patch(route('notification.update', $notification->id))
        ->assertNotFound();
});

it('does not fill the detail drawer from another organization', function (): void {
    $ours = Organization::factory()->create();
    $owner = User::factory()->forOrganization($ours)->create();

    $theirs = Organization::factory()->create();
    $stranger = User::factory()->forOrganization($theirs)->create();

    $membership = strangerMembership($stranger, $theirs);

    $this->actingAs($owner)
        ->get(route('organization-member.edit', ['peek' => $membership->id]))
        ->assertNotFound();
});

it("does not show another organization's import batch", function (): void {
    $ours = Organization::factory()->create();
    $owner = User::factory()->forOrganization($ours)->create();

    $theirs = Organization::factory()->create();
    $stranger = User::factory()->forOrganization($theirs)->create();

    $batch = resolve(OrganizationContext::class)->runAs(
        $theirs,
        fn (): ImportBatch => ImportBatch::factory()->create([
            'organization_id' => $theirs->id,
            'user_id' => $stranger->id,
        ]),
    );

    $this->actingAs($owner)
        ->get(route('import.show', $batch->id))
        ->assertNotFound();
});

it("does not download another organization's upload", function (): void {
    $ours = Organization::factory()->create();
    $owner = User::factory()->forOrganization($ours)->create();

    $theirs = Organization::factory()->create();
    $stranger = User::factory()->forOrganization($theirs)->create();

    $upload = resolve(OrganizationContext::class)->runAs(
        $theirs,
        fn (): TempUpload => TempUpload::factory()->create([
            'organization_id' => $theirs->id,
            'user_id' => $stranger->id,
            'promoted_at' => now(),
        ]),
    );

    $this->actingAs($owner)
        ->get(route('import.download', $upload->id))
        ->assertNotFound();
});
