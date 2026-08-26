<?php

declare(strict_types=1);

namespace Tests\Fixtures\Resources\Incomplete;

use App\Data\UserData;
use App\Models\User;
use App\Resources\ResourceContract;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * A resource that satisfies the interface and nothing else: the search methods
 * are present because PHP demands they be, and they say nothing true. This is
 * what someone writes when they stub a method to make the file load, and it is
 * exactly the state the convention guard has to reject — an interface can only
 * check that a method exists, never that it means anything.
 *
 * Never registered: it lives under tests/Fixtures, so the registry does not see
 * it and only the guard test instantiates it.
 */
final class IncompleteResource implements ResourceContract
{
    public function key(): string
    {
        return 'incomplete';
    }

    public function label(): string
    {
        return 'Incomplete';
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
     * Nothing to match on, so this resource can never be found — and a column
     * that is not a column, so the one entry it does name is a lie.
     *
     * @return list<string>
     */
    public function searchable(): array
    {
        return ['nickname'];
    }

    /**
     * A column that is not a column, so ordering by it would fail at query time. The guard has to say so before a request does.
     *
     * @return list<string>
     */
    public function sortable(): array
    {
        return ['nickname'];
    }

    public function recordLabel(Model $record): string
    {
        return '';
    }

    public function recordDescription(Model $record): ?string
    {
        return null;
    }

    /**
     * @return Builder<User>
     */
    public function scopedQuery(): Builder
    {
        return User::query();
    }
}
