<?php

declare(strict_types=1);

namespace App\Resources\Definitions;

use App\Data\UserData;
use App\Models\User;
use App\Resources\ResourceContract;
use Illuminate\Database\Eloquent\Model;

final class UserResource implements ResourceContract
{
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
}
