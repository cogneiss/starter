<?php

declare(strict_types=1);

use App\Models\TempUpload;
use Illuminate\Support\Facades\Storage;

beforeEach(function (): void {
    Storage::fake('temp-uploads');
});

/**
 * Retention is enforced by deleting, not by hiding.
 *
 * An expired upload leaves both the row and the bytes: a row with no file is a
 * broken download, and a file with no row is a copy of somebody's member list
 * nothing in the application knows about any more.
 */
it('PruneUploads deletes the row and the bytes of an expired upload', function (): void {
    Storage::disk('temp-uploads')->put('imports/old.csv', 'email,role');
    Storage::disk('temp-uploads')->put('imports/new.csv', 'email,role');

    $expired = TempUpload::factory()->expired()->create(['path' => 'imports/old.csv']);
    $current = TempUpload::factory()->create(['path' => 'imports/new.csv']);

    $this->artisan('uploads:prune')->assertSuccessful();

    Storage::disk('temp-uploads')->assertMissing('imports/old.csv');
    Storage::disk('temp-uploads')->assertExists('imports/new.csv');

    $this->assertDatabaseMissing('temp_uploads', ['id' => $expired->id]);
    $this->assertDatabaseHas('temp_uploads', ['id' => $current->id]);
});

/**
 * The command runs on a schedule with no organization bound, so it has to reach
 * across every tenant. Two organizations' expired uploads, one pass.
 */
it('PruneUploads reaches every organization', function (): void {
    Storage::disk('temp-uploads')->put('imports/a.csv', 'email,role');
    Storage::disk('temp-uploads')->put('imports/b.csv', 'email,role');

    TempUpload::factory()->expired()->create(['path' => 'imports/a.csv']);
    TempUpload::factory()->expired()->create(['path' => 'imports/b.csv']);

    $this->artisan('uploads:prune')->assertSuccessful();

    Storage::disk('temp-uploads')->assertMissing('imports/a.csv');
    Storage::disk('temp-uploads')->assertMissing('imports/b.csv');

    expect(TempUpload::withoutOrganizationScope()->count())->toBe(0);
});
