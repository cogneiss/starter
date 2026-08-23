<?php

declare(strict_types=1);

namespace App\Resources\Definitions;

use App\Data\OrganizationData;
use App\Models\Organization;
use App\Policies\OrganizationPolicy;
use App\Resources\ResourceContract;
use Illuminate\Database\Eloquent\Model;

final class OrganizationResource implements ResourceContract
{
    public function key(): string
    {
        return 'organizations';
    }

    public function label(): string
    {
        return 'Organizations';
    }

    public function model(): string
    {
        return Organization::class;
    }

    public function dataClass(): string
    {
        return OrganizationData::class;
    }

    public function policy(): string
    {
        return OrganizationPolicy::class;
    }

    /**
     * The organization you are looking at is always the one bound to the
     * request, so its settings screen takes no parameter.
     */
    public function url(Model $record): string
    {
        return route('organization.edit');
    }
}
