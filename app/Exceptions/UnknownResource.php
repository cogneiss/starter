<?php

declare(strict_types=1);

namespace App\Exceptions;

use RuntimeException;

/**
 * Thrown when something asks the registry for a resource key that no adapter
 * claims, or when two adapters claim the same key.
 */
final class UnknownResource extends RuntimeException
{
    /**
     * @param  list<string>  $known
     */
    public static function key(string $key, array $known): self
    {
        return new self(
            "No resource adapter is registered for the key [{$key}]. ".
            'Known keys: '.($known === [] ? '(none)' : implode(', ', $known)).'.'
        );
    }

    public static function duplicateKey(string $key, string $first, string $second): self
    {
        return new self(
            "Two resource adapters claim the key [{$key}]: [{$first}] and [{$second}]. ".
            'Resource keys must be unique — rename one of them.'
        );
    }
}
