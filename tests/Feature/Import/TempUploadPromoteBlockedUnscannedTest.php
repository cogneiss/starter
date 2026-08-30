<?php

declare(strict_types=1);

use App\Actions\PromoteTempUpload;
use App\Models\ImportBatch;
use App\Models\Organization;
use App\Models\TempUpload;
use App\Models\User;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;

beforeEach(function (): void {
    Notification::fake();
    Storage::fake('temp-uploads');
});

/**
 * An absent verdict is not a clean one.
 *
 * The whole quarantine is one comparison, so it is worth stating all three of
 * its answers: clean promotes, infected does not, and neither does an upload
 * the scanner has not reached yet.
 */
it('TempUploadPromoteBlockedUnscanned refuses an upload with no verdict', function (): void {
    $promote = resolve(PromoteTempUpload::class);

    $unscanned = TempUpload::factory()->create();
    $infected = TempUpload::factory()->infected()->create();
    $clean = TempUpload::factory()->clean()->create();

    expect($promote->handle($unscanned))->toBeFalse()
        ->and($unscanned->fresh()?->promoted_at)->toBeNull()
        ->and($promote->handle($infected))->toBeFalse()
        ->and($infected->fresh()?->promoted_at)->toBeNull()
        ->and($promote->handle($clean))->toBeTrue()
        ->and($clean->fresh()?->promoted_at)->not->toBeNull();
});

/**
 * The parser waits rather than reading ahead of the scanner. Nothing is parsed,
 * nothing is written, and the job goes back on the queue.
 */
it('TempUploadPromoteBlockedUnscanned does not read a file the scanner has not reached', function (): void {
    $organization = Organization::factory()->create();
    $owner = User::factory()->forOrganization($organization)->create();

    $batch = uploadedImport($owner, $organization, "email,role\na@example.com,Member\n", 'unscanned');

    runImport($batch, $owner);

    expect($batch->rows()->count())->toBe(0)
        ->and($batch->fresh()?->status)->not->toBe('complete');
});

it('TempUploadPromoteBlockedUnscanned rejects the batch when the scanner refused the file', function (): void {
    $organization = Organization::factory()->create();
    $owner = User::factory()->forOrganization($organization)->create();

    $batch = uploadedImport($owner, $organization, "email,role\na@example.com,Member\n", 'infected');

    runImport($batch, $owner);

    expect($batch->rows()->count())->toBe(0)
        ->and($batch->fresh()?->status)->toBe('rejected');
});

it('TempUploadPromoteBlockedUnscanned does nothing for a batch with no file', function (): void {
    $organization = Organization::factory()->create();
    $owner = User::factory()->forOrganization($organization)->create();

    $batch = ImportBatch::factory()->create([
        'organization_id' => $organization->id,
        'user_id' => $owner->id,
        'temp_upload_id' => null,
    ]);

    runImport($batch, $owner);

    expect($batch->rows()->count())->toBe(0)
        ->and($batch->fresh()?->status)->not->toBe('complete');
});
