<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\DismissAnnouncement;
use App\Actions\VisibleAnnouncements;
use App\Models\User;
use App\Support\OrganizationContext;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\RedirectResponse;

final readonly class AnnouncementDismissalController
{
    /**
     * An announcement the member cannot see does not exist for them: the lookup
     * is the visibility query, so a foreign or out-of-window id is a 404.
     */
    public function store(string $announcement, #[CurrentUser] User $user, OrganizationContext $context, DismissAnnouncement $action, VisibleAnnouncements $visible): RedirectResponse
    {
        $action->handle($user, $visible->handle((string) $context->id())->findOrFail($announcement));

        return back();
    }
}
