<?php

declare(strict_types=1);

use Symfony\Component\Finder\Finder;

/**
 * Models are unguarded (config/essentials.php), so the form request is the only
 * thing between the browser and a column. That holds while controllers and
 * actions read validated input. Raw input — all(), except(), a bare input() or
 * post() — would carry any field the client sends straight into create() or
 * fill(), so it is banned where models get written.
 *
 * Every parameter typed as a request is checked, whatever it is named, plus the
 * request() helper.
 */
it('keeps raw request input out of controllers and actions', function (): void {
    $offenders = [];

    foreach (Finder::create()->files()->in([app_path('Http'), app_path('Actions')])->name('*.php') as $file) {
        $contents = $file->getContents();

        preg_match_all('/\b\w*Request\s+\$(\w+)/', $contents, $matches);
        $receivers = array_map(fn (string $name): string => '\$'.$name, array_unique($matches[1]));
        $receivers[] = 'request\(\)';
        $receiver = '(?:'.implode('|', $receivers).')';

        if (preg_match('/'.$receiver.'->(?:(?:all|post|input)\(\s*\)|except\()/', $contents) === 1) {
            $offenders[] = str_replace(base_path().'/', '', $file->getPathname());
        }
    }

    expect($offenders)->toBe([]);
});
