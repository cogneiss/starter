<?php

declare(strict_types=1);

use Symfony\Component\Finder\Finder;

/**
 * Models are unguarded (config/essentials.php), so the form request is the only
 * thing between the browser and a column. That holds while controllers and
 * actions read validated input. Raw input — all(), except(), a bare input() or
 * post() — would carry any field the client sends straight into create() or
 * fill(), so it is banned where models get written.
 */
it('keeps raw request input out of controllers and actions', function (): void {
    $offenders = [];

    foreach (Finder::create()->files()->in([app_path('Http'), app_path('Actions')])->name('*.php') as $file) {
        if (preg_match('/(\$request|request\(\))->(all|post|input)\(\s*\)|(\$request|request\(\))->except\(/', $file->getContents()) === 1) {
            $offenders[] = str_replace(base_path().'/', '', $file->getPathname());
        }
    }

    expect($offenders)->toBe([]);
});
