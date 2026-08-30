<?php

declare(strict_types=1);

namespace App\Contracts;

/**
 * Whatever this deployment uses to decide an uploaded file is safe.
 *
 * A scan is a call to something outside the process, so it never happens in a
 * request. {@see \App\Jobs\ScanTempUpload} is the only caller.
 */
interface FileScanner
{
    public const string CLEAN = 'clean';

    public const string INFECTED = 'infected';

    /**
     * @return self::CLEAN|self::INFECTED
     */
    public function scan(string $disk, string $path): string;

    /**
     * What to tell an operator this scanner is, for `app:doctor`.
     */
    public function describe(): string;
}
