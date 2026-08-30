<?php

declare(strict_types=1);

use App\Jobs\ParseImportBatch;
use App\Models\ImportRow;
use App\Models\Organization;
use App\Models\OrganizationInvitation;
use App\Models\User;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;

beforeEach(function (): void {
    Notification::fake();
    Storage::fake('temp-uploads');
});

/**
 * A retry runs the failures again and nothing else.
 *
 * The rows are already stored, so re-running is not re-uploading: the lines that
 * worked are left exactly as they were, which is what stops a second attempt
 * inviting everybody twice.
 */
it('ImportRetry runs the failed rows again and leaves the imported ones alone', function (): void {
    $organization = Organization::factory()->create();
    $owner = User::factory()->forOrganization($organization)->create();

    $batch = uploadedImport($owner, $organization, <<<'CSV'
        email,role
        good@example.com,Member
        not-an-email,Member
        CSV);

    runImport($batch, $owner);

    expect(OrganizationInvitation::withoutOrganizationScope()->count())->toBe(1);

    // The correction the operator would have made, applied to the stored row.
    $failed = $batch->rows()->where('status', ImportRow::FAILED)->sole();
    $failed->forceFill(['data' => ['email' => 'fixed@example.com', 'role' => 'Member']])->save();

    runImport($batch, $owner, onlyFailures: true);

    expect($batch->rows()->where('status', ImportRow::IMPORTED)->count())->toBe(2)
        ->and(OrganizationInvitation::withoutOrganizationScope()->pluck('email')->sort()->values()->all())
        ->toBe(['fixed@example.com', 'good@example.com']);
});

it('ImportRetry from the browser only dispatches', function (): void {
    Queue::fake();

    $organization = Organization::factory()->create();
    $owner = User::factory()->forOrganization($organization)->create();

    $batch = uploadedImport($owner, $organization, "email,role\ngood@example.com,Member\n");

    $this->actingAs($owner)
        ->post(route('import.retry', ['batch' => $batch->id]))
        ->assertRedirect(route('import.show', ['batch' => $batch->id]));

    Queue::assertPushed(ParseImportBatch::class);
});
