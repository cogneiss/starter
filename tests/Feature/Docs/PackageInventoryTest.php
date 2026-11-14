<?php

declare(strict_types=1);

const PACKAGE_INVENTORY = 'wiki/operations/packages.md';

/**
 * Every package named in composer.json and package.json, in both the runtime
 * and the development lists. `php` is a platform requirement, not a package.
 *
 * @return list<string>
 */
function installedPackages(): array
{
    return collect(['composer.json' => ['require', 'require-dev'], 'package.json' => ['dependencies', 'devDependencies']])
        ->flatMap(function (array $sections, string $file): array {
            /** @var array<string, array<string, string>> $manifest */
            $manifest = json_decode((string) file_get_contents(base_path($file)), true, flags: JSON_THROW_ON_ERROR);

            return collect($sections)->flatMap(fn (string $section): array => array_keys($manifest[$section] ?? []))->all();
        })
        ->reject(fn (string $name): bool => $name === 'php')
        ->values()
        ->all();
}

/**
 * The package each table row names in its first cell.
 *
 * @return list<string>
 */
function inventoryRows(): array
{
    preg_match_all('/^\| `([^`]+)` \|/m', (string) file_get_contents(base_path(PACKAGE_INVENTORY)), $matches);

    return $matches[1];
}

it('has a row for every installed package', function (): void {
    $missing = array_values(array_diff(installedPackages(), inventoryRows()));

    expect($missing)->toBe([], sprintf(
        '%s has no row for: %s. Add one saying what the package does here and where it is configured.',
        PACKAGE_INVENTORY,
        implode(', ', $missing),
    ));
});

it('has no row for a package that is no longer installed', function (): void {
    $stale = array_values(array_diff(inventoryRows(), installedPackages()));

    expect($stale)->toBe([], sprintf(
        '%s still lists: %s. Remove the rows.',
        PACKAGE_INVENTORY,
        implode(', ', $stale),
    ));
});
