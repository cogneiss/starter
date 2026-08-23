<?php

declare(strict_types=1);

namespace App\Support;

/**
 * One permission the application knows about. Names are `<resource>.<verb>`.
 */
final readonly class PermissionDefinition
{
    public function __construct(
        public string $name,
        public string $group,
        public string $label,
        public string $description,
    ) {}
}
