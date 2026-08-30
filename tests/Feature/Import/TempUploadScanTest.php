<?php

declare(strict_types=1);

use App\Contracts\FileScanner;
use App\Jobs\ScanTempUpload;
use App\Models\Organization;
use App\Models\TempUpload;
use App\Models\User;
use App\Queue\Middleware\WithOrganizationContext;
use App\Support\OrganizationContext;
use App\Support\Scanners\ClamAvScanner;
use App\Support\Scanners\NullScanner;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Storage;

beforeEach(function (): void {
    Storage::fake('temp-uploads');
});

function unscannedUpload(): TempUpload
{
    $organization = Organization::factory()->create();
    $user = User::factory()->forOrganization($organization)->create();

    Storage::disk('temp-uploads')->put('imports/file.csv', 'email,role');

    return TempUpload::factory()->create([
        'organization_id' => $organization->id,
        'user_id' => $user->id,
        'path' => 'imports/file.csv',
    ]);
}

/**
 * The verdict is recorded on the upload, whatever it is. Nothing downstream asks
 * the scanner again — it asks the row.
 */
it('TempUploadScan records the verdict on the upload', function (): void {
    $upload = unscannedUpload();
    $job = new ScanTempUpload($upload->id, $upload->organization_id);

    expect($job->organizationId())->toBe($upload->organization_id)
        ->and($job->middleware())->toContainOnlyInstancesOf(WithOrganizationContext::class);

    OrganizationContext::run($upload->organization, fn () => $job->handle(new NullScanner));

    $upload->refresh();

    expect($upload->scan_result)->toBe(FileScanner::CLEAN)
        ->and($upload->scanned_at)->not->toBeNull();
});

/**
 * A deployment with no scanner still imports files. It says so in the log rather
 * than letting the absence pass for an inspection.
 */
it('TempUploadScan without a scanner says so out loud', function (): void {
    Log::spy();

    expect((new NullScanner)->scan('temp-uploads', 'imports/file.csv'))->toBe(FileScanner::CLEAN)
        ->and((new NullScanner)->describe())->toContain('unscanned');

    Log::shouldHaveReceived('warning')->once();
});

it('TempUploadScan reads clamav by its exit code', function (): void {
    Storage::disk('temp-uploads')->put('imports/file.csv', 'email,role');

    Process::fake(['*clamdscan*' => Process::result(exitCode: 0)]);

    expect((new ClamAvScanner)->scan('temp-uploads', 'imports/file.csv'))->toBe(FileScanner::CLEAN);

    Process::fake(['*clamdscan*' => Process::result(exitCode: 1)]);

    expect((new ClamAvScanner)->scan('temp-uploads', 'imports/file.csv'))->toBe(FileScanner::INFECTED)
        ->and((new ClamAvScanner)->describe())->toContain('clamdscan');
});
