<?php

declare(strict_types=1);

namespace App\Imports;

use InvalidArgumentException;

/**
 * Every importable resource the application knows about.
 *
 * A named list rather than a directory scan: an import writes records, so which
 * ones exist is a decision somebody makes here rather than something a file
 * dropped into a folder quietly grants.
 */
final class ImportRegistry
{
    /**
     * @var list<class-string<ImportContract>>
     */
    private const array IMPORTS = [
        OrganizationInvitationImport::class,
    ];

    public function get(string $key): ImportContract
    {
        foreach (self::IMPORTS as $class) {
            $import = resolve($class);

            if ($import->key() === $key) {
                return $import;
            }
        }

        throw new InvalidArgumentException("No import is registered for [{$key}].");
    }
}
