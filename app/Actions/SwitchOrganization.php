<?php

declare(strict_types=1);

namespace App\Actions;

use App\Models\Organization;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;

final readonly class SwitchOrganization
{
    /**
     * Point the user's session at another organization they actively belong to.
     *
     * @throws AuthorizationException
     */
    public function handle(User $user, Organization $organization): void
    {
        throw_unless($user->belongsToOrganization($organization), AuthorizationException::class);

        $user->forceFill(['current_organization_id' => $organization->id])->save();

        session()->regenerate();
    }
}
