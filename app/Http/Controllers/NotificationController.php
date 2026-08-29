<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\User;
use App\Support\OrganizationContext;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Notifications\DatabaseNotification;

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
        $this->unread($user, $context)->findOrFail($notification)->markAsRead();

        return back();
    }

    /**
     * Mark every notification in this organization read.
     */
    public function updateAll(#[CurrentUser] User $user, OrganizationContext $context): RedirectResponse
    {
        $this->unread($user, $context)->update(['read_at' => now()]);

        return back();
    }

    /**
     * @return Builder<DatabaseNotification>
     */
    private function unread(User $user, OrganizationContext $context): Builder
    {
        return $user->unreadNotifications()
            ->getQuery()
            ->where('organization_id', $context->id());
    }
}
