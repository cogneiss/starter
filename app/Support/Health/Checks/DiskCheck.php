<?php

declare(strict_types=1);

namespace App\Support\Health\Checks;

use Spatie\Health\Checks\Check;
use Spatie\Health\Checks\Result;

final class DiskCheck extends Check
{
    protected ?string $name = 'disk';

    /**
     * Free-space floor on a configured path. Never a hard failure, and a path
     * that cannot be measured warns, where the package's df-based check would
     * read an empty answer as 0% used.
     */
    public function run(): Result
    {
        return rescue(static function (): Result {
            $path = config()->string('health.disk_path');

            $free = disk_free_space($path);
            $total = disk_total_space($path);

            assert($free !== false && $total !== false, 'Laravel turns the disk_*_space warning into an exception, which rescue() reports as a warning.');

            $percentFree = $free / $total * 100;

            return $percentFree >= config()->float('health.disk_minimum_free_percent')
                ? Result::make()->ok()
                : Result::make()->warning('Free disk space is below the configured floor.');
        }, static fn (): Result => Result::make()->warning('The disk could not be measured.'), report: false);
    }
}
