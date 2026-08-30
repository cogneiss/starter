<?php

declare(strict_types=1);

namespace App\Support\Scanners;

use App\Contracts\FileScanner;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Storage;

/**
 * Hands the file to a local clamd through `clamdscan`.
 *
 * clamdscan exits 0 for a clean file and 1 for an infection. Anything else is a
 * scanner that could not answer, and an unanswered scan is treated as an
 * infection: the alternative is promoting a file nobody checked.
 */
final readonly class ClamAvScanner implements FileScanner
{
    public function __construct(private string $binary = 'clamdscan') {}

    public function scan(string $disk, string $path): string
    {
        $result = Process::run([$this->binary, '--fdpass', '--no-summary', Storage::disk($disk)->path($path)]);

        return $result->exitCode() === 0 ? self::CLEAN : self::INFECTED;
    }

    public function describe(): string
    {
        return 'clamav ('.$this->binary.')';
    }
}
