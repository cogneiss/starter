<?php

declare(strict_types=1);

namespace App\Actions;

use App\Models\Organization;

final readonly class UpdateOrganization
{
    /**
     * @param  array<string, mixed>  $attributes
     */
    public function handle(Organization $organization, array $attributes): Organization
    {
        $organization->update($attributes);

        return $organization;
    }
}
