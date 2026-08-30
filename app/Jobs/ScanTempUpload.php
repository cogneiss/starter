<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Contracts\FileScanner;
use App\Contracts\OrganizationAware;
use App\Models\TempUpload;
use App\Queue\Middleware\WithOrganizationContext;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Hands one upload to whichever scanner this deployment configured.
 *
 * Queued rather than inline: a scanner is another process, sometimes another
 * machine, and a request that waits for it is a request that times out under
 * the one condition the scanner exists for.
 */
final class ScanTempUpload implements OrganizationAware, ShouldQueue
{
    use Queueable;

    public function __construct(
        private readonly string $uploadId,
        private readonly string $organizationId,
    ) {}

    public function organizationId(): string
    {
        return $this->organizationId;
    }

    /**
     * @return list<object>
     */
    public function middleware(): array
    {
        return [new WithOrganizationContext];
    }

    public function handle(FileScanner $scanner): void
    {
        $upload = TempUpload::query()->findOrFail($this->uploadId);

        $upload->forceFill([
            'scan_result' => $scanner->scan($upload->disk, $upload->path),
            'scanned_at' => now(),
        ])->save();
    }
}
