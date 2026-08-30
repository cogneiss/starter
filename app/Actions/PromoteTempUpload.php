<?php

declare(strict_types=1);

namespace App\Actions;

use App\Contracts\FileScanner;
use App\Models\TempUpload;

/**
 * Lets an upload out of quarantine.
 *
 * Promotion is the only door between an uploaded file and anything that reads
 * it, and it opens on the scan verdict alone. An upload nobody has scanned yet
 * is refused exactly like an infected one: an absent verdict is not a clean
 * one, and treating it as clean would make the whole seam decorative.
 */
final readonly class PromoteTempUpload
{
    public function handle(TempUpload $upload): bool
    {
        if ($upload->scan_result !== FileScanner::CLEAN) {
            return false;
        }

        $upload->forceFill(['promoted_at' => now()])->save();

        return true;
    }
}
