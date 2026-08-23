<?php

declare(strict_types=1);

namespace App\Resources\Definitions;

use App\Data\OrganizationMemberData;
use App\Models\OrganizationMembership;
use App\Policies\OrganizationMembershipPolicy;
use App\Resources\ResourceContract;
use Illuminate\Database\Eloquent\Model;

final class OrganizationMemberResource implements ResourceContract
{
    public function key(): string
    {
        return 'organization-members';
    }

    public function label(): string
    {
        return 'Organization members';
    }

    public function model(): string
    {
        return OrganizationMembership::class;
    }

    public function dataClass(): string
    {
        return OrganizationMemberData::class;
    }

    public function policy(): string
    {
        return OrganizationMembershipPolicy::class;
    }

    public function url(Model $record): string
    {
        return route('organization-member.edit');
    }
}
