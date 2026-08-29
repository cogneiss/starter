<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\User;
use App\Support\OrganizationContext;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\RedirectResponse;

/**
 * Mark every notification in this organization read.
 *
 * The update runs as one statement over the query that already carries the user
 * and the organization, so it can only ever touch this person's rows in the
 * organization they are looking at.
 */
final readonly class NotificationBulkController
{
    public function __invoke(#[CurrentUser] User $user, OrganizationContext $context): RedirectResponse
    {
        $user->unreadNotifications()
            ->getQuery()
            ->where('organization_id', $context->id())
            ->update(['read_at' => now()]);

        return back();
    }
}
