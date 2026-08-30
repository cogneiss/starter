<?php

declare(strict_types=1);

use App\Models\ImportRow;
use App\Models\Organization;
use App\Models\OrganizationInvitation;
use App\Models\User;
use App\Support\OrganizationContext;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;

/**
 * A worker keeps its container between jobs, so whatever the last batch bound is
 * still bound when the next one starts.
 *
 * This test hands the job an organization that is not the one already in the
 * container. Without the rebind the rows and the invitations would be written
 * into the previous tenant — silently, and invisibly to any test that runs a
 * single batch on a clean container.
 */
it('ImportJobRebindsOrganization writes into the batch organization, not the one already bound', function (): void {
    Notification::fake();
    Storage::fake('temp-uploads');

    $previous = Organization::factory()->create();
    $organization = Organization::factory()->create();
    $owner = User::factory()->forOrganization($organization)->create();

    $batch = uploadedImport($owner, $organization, "email,role\nsomebody@example.com,Member\n");

    $context = resolve(OrganizationContext::class);
    $context->set($previous);

    runImport($batch, $owner);

    // Restored afterwards: the job borrowed the container, it did not keep it.
    expect($context->id())->toBe($previous->id);

    $invitation = OrganizationInvitation::withoutOrganizationScope()->sole();

    expect($invitation->organization_id)->toBe($organization->id)
        ->and($invitation->email)->toBe('somebody@example.com');

    expect($batch->rows()->where('status', ImportRow::IMPORTED)->count())->toBe(1);
});
