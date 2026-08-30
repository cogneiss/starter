<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\TempUpload;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Storage;

/**
 * Deletes uploads nobody came back for.
 *
 * A row deleted without its file leaves the bytes on disk forever, and a file
 * deleted without its row leaves a record pointing at nothing, so both go in the
 * same pass. This runs across every organization by design — expiry is a
 * housekeeping fact, not a tenant's decision — which is why it is scheduled
 * rather than reachable from a request.
 */
#[Description('Delete temporary uploads whose retention window has passed')]
#[Signature('uploads:prune')]
final class PruneUploadsCommand extends Command
{
    private const int CHUNK = 200;

    public function handle(): int
    {
        $deleted = 0;

        TempUpload::withoutOrganizationScope()
            ->where('expires_at', '<=', now())
            ->chunkById(self::CHUNK, function (Collection $uploads) use (&$deleted): void {
                foreach ($uploads as $upload) {
                    Storage::disk($upload->disk)->delete($upload->path);
                    $upload->delete();
                    $deleted++;
                }
            });

        $this->components->info(sprintf('Pruned %d expired upload(s).', $deleted));

        return self::SUCCESS;
    }
}
