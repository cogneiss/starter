<?php

declare(strict_types=1);

namespace Tests\Fixtures\Resources\Valid;

use App\Data\UserData;
use App\Models\User;
use App\Resources\ResourceColumn;
use App\Resources\ResourceContract;
use App\Support\ResourceFilter;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

final class FixtureResource implements ResourceContract
{
    public function key(): string
    {
        return 'fixtures';
    }

    public function label(): string
    {
        return 'Fixtures';
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

    public function url(Model $record): string
    {
        return route('dashboard');
    }

    /**
     * @return list<string>
     */
    public function searchable(): array
    {
        return ['name'];
    }

    /**
     * @return list<string>
     */
    public function sortable(): array
    {
        return ['name'];
    }

    /**
     * @return list<ResourceFilter>
     */
    public function filters(): array
    {
        return [];
    }

    /**
     * @return list<ResourceColumn>
     */
    public function columns(): array
    {
        return [new ResourceColumn(key: 'name', label: 'Name')];
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
     * @return Builder<User>
     */
    public function scopedQuery(): Builder
    {
        return User::query();
    }
}
