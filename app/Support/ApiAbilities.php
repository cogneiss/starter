<?php

declare(strict_types=1);

namespace App\Support;

use App\Resources\ResourceRegistry;

/**
 * The abilities an API token may hold, derived from the resource registry at
 * call time. Registering a resource adds its `read:<key>` ability everywhere —
 * the create form, validation and the catalogue — with no code change here.
 */
final class ApiAbilities
{
    /**
     * @return list<string>
     */
    public static function all(): array
    {
        return array_map(
            static fn (string $key): string => 'read:'.$key,
            resolve(ResourceRegistry::class)->keys(),
        );
    }
}
