<?php

declare(strict_types=1);

use App\Models\Organization;
use App\Models\TempUpload;
use App\Models\User;
use Illuminate\Support\Facades\Storage;

beforeEach(function (): void {
    Storage::fake('temp-uploads');
});

/**
 * The bytes are not on the web.
 *
 * The disk is private and unserved, so the only route to an uploaded file is the
 * application's own, which asks the same questions of the reader as everything
 * else does.
 */
it('TempUploadNotDirectlyDownloadable keeps the disk private and unserved', function (): void {
    expect(config('filesystems.disks.temp-uploads.visibility'))->toBe('private')
        ->and(config('filesystems.disks.temp-uploads.serve'))->toBeFalse()
        ->and(config('filesystems.disks.temp-uploads.root'))->toBe(storage_path('app/temp-uploads'));
});

it('TempUploadNotDirectlyDownloadable does not serve a file still in quarantine', function (): void {
    $organization = Organization::factory()->create();
    $owner = User::factory()->forOrganization($organization)->create();

    Storage::disk('temp-uploads')->put('imports/quarantined.csv', 'email,role');

    $upload = TempUpload::factory()->create([
        'organization_id' => $organization->id,
        'user_id' => $owner->id,
        'path' => 'imports/quarantined.csv',
    ]);

    $this->actingAs($owner)
        ->get(route('import.download', ['upload' => $upload->id]))
        ->assertNotFound();
});

it('TempUploadNotDirectlyDownloadable serves a promoted file to the person who uploaded it', function (): void {
    $organization = Organization::factory()->create();
    $owner = User::factory()->forOrganization($organization)->create();

    Storage::disk('temp-uploads')->put('imports/promoted.csv', 'email,role');

    $upload = TempUpload::factory()->clean()->create([
        'organization_id' => $organization->id,
        'user_id' => $owner->id,
        'path' => 'imports/promoted.csv',
        'promoted_at' => now(),
    ]);

    $this->actingAs($owner)
        ->get(route('import.download', ['upload' => $upload->id]))
        ->assertOk()
        ->assertDownload($upload->original_name);
});
