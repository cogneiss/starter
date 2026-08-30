<?php

declare(strict_types=1);

use App\Jobs\ParseImportBatch;
use App\Jobs\ScanTempUpload;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;

/**
 * The request stores bytes and dispatches. It does not open the file.
 *
 * A file of a hundred thousand lines takes as long as a file of ten from the
 * browser's point of view, and the scanner gets its turn before anything reads
 * a single line — neither of which is true if the parsing happens here.
 */
it('ImportQueued stores the file and dispatches instead of parsing in the request', function (): void {
    Queue::fake();
    Storage::fake('temp-uploads');

    $organization = Organization::factory()->create();
    $owner = User::factory()->forOrganization($organization)->create();

    $this->actingAs($owner)
        ->post(route('import.store', ['import' => 'organization-invitations']), [
            'file' => UploadedFile::fake()->createWithContent(
                'members.csv',
                "email,role\nnew@example.com,Member\n",
            ),
        ])
        ->assertRedirect();

    Queue::assertPushed(ScanTempUpload::class);
    Queue::assertPushed(ParseImportBatch::class);

    $this->assertDatabaseCount('temp_uploads', 1);
    $this->assertDatabaseCount('import_batches', 1);

    // Nothing was read, so nothing was written.
    $this->assertDatabaseCount('import_rows', 0);
    $this->assertDatabaseCount('organization_invitations', 0);
});
