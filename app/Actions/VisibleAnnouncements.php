<?php

declare(strict_types=1);

namespace App\Actions;

use App\Models\Announcement;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

final readonly class VisibleAnnouncements
{
    /**
     * Inside its window, and global or addressed to this organization. The
     * organization is a where clause, never a check on the rows that come back.
     * With a user, what that user already dismissed is left out.
     *
     * @return Builder<Announcement>
     */
    public function handle(string $organizationId, ?User $undismissedBy = null): Builder
    {
        return Announcement::query()
            ->where('starts_at', '<=', now())
            ->where(fn (Builder $window): Builder => $window->whereNull('ends_at')->orWhere('ends_at', '>', now()))
            ->where(fn (Builder $audience): Builder => $audience->whereNull('organization_id')->orWhere('organization_id', $organizationId))
            ->when($undismissedBy instanceof User, fn (Builder $query): Builder => $query->whereDoesntHave(
                'dismissedBy',
                fn (Builder $dismissals): Builder => $dismissals->whereKey($undismissedBy?->id),
            ));
    }
}
