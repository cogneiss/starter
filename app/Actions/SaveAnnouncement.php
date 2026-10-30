<?php

declare(strict_types=1);

namespace App\Actions;

use App\Models\Announcement;

final readonly class SaveAnnouncement
{
    /**
     * Create or update. The audit entry comes from `audit.extra`, so the admin
     * who saved it is on the ledger without this action writing a second one.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function handle(?Announcement $announcement, array $attributes): Announcement
    {
        $announcement ??= new Announcement();

        $announcement->fill($attributes)->save();

        return $announcement;
    }
}
