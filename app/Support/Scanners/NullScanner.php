<?php

declare(strict_types=1);

namespace App\Support\Scanners;

use App\Contracts\FileScanner;
use Illuminate\Support\Facades\Log;

/**
 * The default: nothing is scanned and everything is called clean.
 *
 * It logs every verdict rather than staying quiet, and reports itself through
 * `app:doctor`, because a production deployment that never configured a real
 * scanner should be able to find that out from the outside.
 */
final class NullScanner implements FileScanner
{
    public function scan(string $disk, string $path): string
    {
        Log::warning('No file scanner is configured; the upload was accepted unscanned.', [
            'disk' => $disk,
            'path' => $path,
        ]);

        return self::CLEAN;
    }

    public function describe(): string
    {
        return 'null (uploads are accepted unscanned)';
    }
}
