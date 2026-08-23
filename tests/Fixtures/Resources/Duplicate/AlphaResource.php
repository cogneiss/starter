<?php

declare(strict_types=1);

namespace Tests\Fixtures\Resources\Duplicate;

use App\Data\UserData;
use App\Models\User;
use App\Resources\ResourceContract;
use Illuminate\Database\Eloquent\Model;

final class AlphaResource implements ResourceContract
{
    public function key(): string
    {
        return 'clashing';
    }

    public function label(): string
    {
        return 'Alpha';
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
}
