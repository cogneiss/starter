<?php

declare(strict_types=1);

namespace App\Actions;

use App\Models\AiMemory;
use App\Models\User;
use Illuminate\Support\Facades\DB;

final readonly class DeleteUser
{
    /**
     * Deleting the person deletes what the assistant remembered about them,
     * in every organization. The memory table is not organization-scoped by a
     * global scope, so the purge says so itself rather than inheriting whatever
     * organization happens to be bound to the request doing the deleting.
     */
    public function handle(User $user): void
    {
        DB::transaction(function () use ($user): void {
            AiMemory::query()->where('user_id', $user->id)->delete();

            $user->delete();
        });
    }
}
