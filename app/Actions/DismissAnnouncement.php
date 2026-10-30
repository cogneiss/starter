<?php

declare(strict_types=1);

namespace App\Actions;

use App\Models\Announcement;
use App\Models\User;

final readonly class DismissAnnouncement
{
    /**
     * Idempotent: dismissing twice keeps the first timestamp.
     */
    public function handle(User $user, Announcement $announcement): void
    {
        $announcement->dismissedBy()->syncWithoutDetaching([$user->id => ['dismissed_at' => now()]]);
    }
}
