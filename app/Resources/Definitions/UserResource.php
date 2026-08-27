<?php

declare(strict_types=1);

namespace App\Resources\Definitions;

use App\Data\UserData;
use App\Models\Organization;
use App\Models\User;
use App\Resources\ResourceContract;
use App\Resources\ScopedToOrganization;
use App\Support\ResourceFilter;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

final class UserResource implements ResourceContract
{
    use ScopedToOrganization;

    public function key(): string
    {
        return 'users';
    }

    public function label(): string
    {
        return 'Users';
    }

    public function model(): string
    {
        return User::class;
    }

    public function dataClass(): string
    {
        return UserData::class;
    }

    public function policy(): ?string
    {
        return null;
    }

    /**
     * A user is only ever reachable as themselves: the app has no directory
     * page, so every user links to the signed-in profile screen.
     */
    public function url(Model $record): string
    {
        return route('user-profile.edit');
    }

    /**
     * @return list<string>
     */
    public function searchable(): array
    {
        return ['name', 'email'];
    }

    /**
     * Newest people first is the useful default for a member directory.
     *
     * @return list<string>
     */
    public function sortable(): array
    {
        return ['created_at', 'name', 'email'];
    }

    /**
     * @return list<ResourceFilter>
     */
    public function filters(): array
    {
        return [];
    }

    public function recordLabel(Model $record): string
    {
        assert($record instanceof User);

        return $record->name;
    }

    public function recordDescription(Model $record): string
    {
        assert($record instanceof User);

        return $record->email;
    }

    /**
     * Users are global rows, so the reachable set is the bound organization's
     * membership — the pivot is the where clause.
     *
     * @return Builder<User>
     */
    public function scopedQuery(): Builder
    {
        return $this->scopedToOrganization(
            fn (Organization $organization): Builder => $organization->users()->getQuery(),
        );
    }
}
