<?php

declare(strict_types=1);

namespace App\Data;

use App\Models\Organization;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript('Organization')]
final class OrganizationData extends Data
{
    public function __construct(
        public string $id,
        public string $name,
        public string $slug,
        public bool $personal,
        public bool $require_two_factor,
    ) {}

    public static function fromModel(Organization $organization): self
    {
        return new self(
            id: $organization->id,
            name: $organization->name,
            slug: $organization->slug,
            personal: $organization->personal,
            require_two_factor: $organization->require_two_factor,
        );
    }
}
