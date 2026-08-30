<?php

declare(strict_types=1);

namespace App\Onboarding\Steps;

use App\Models\Organization;
use App\Models\User;
use App\Onboarding\StepContract;

/**
 * Give the organization its colours, so the product stops looking like a demo.
 */
final class BrandOrganizationStep implements StepContract
{
    public function key(): string
    {
        return 'brand';
    }

    public function title(): string
    {
        return 'Brand your organization';
    }

    public function description(): string
    {
        return 'Pick the colours the application wears for your team.';
    }

    public function route(): string
    {
        return 'organization.edit';
    }

    public function isRequired(): bool
    {
        return true;
    }

    public function order(): int
    {
        return 10;
    }

    public function isComplete(User $user, Organization $organization): bool
    {
        return $organization->brand_primary_color !== null;
    }
}
