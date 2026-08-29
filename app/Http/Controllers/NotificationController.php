<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\User;
use App\Support\OrganizationContext;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\RedirectResponse;

final readonly class NotificationController
{
    /**
     * Mark one notification read.
     *
     * The notification is looked up inside the query that already carries the
     * user and the organization, so an id belonging to someone else — or to the
     * same person in another organization — is not found rather than refused.
     */
    public function update(#[CurrentUser] User $user, OrganizationContext $context, string $notification): RedirectResponse
    {
        $user->unreadNotifications()
            ->getQuery()
            ->where('organization_id', $context->id())
            ->findOrFail($notification)
            ->markAsRead();

        return back();
    }
}
