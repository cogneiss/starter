<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\AnnouncementLevel;
use Carbon\CarbonInterface;
use Database\Factories\AnnouncementFactory;
use Illuminate\Database\Eloquent\Attributes\Unguarded;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * A short notice platform staff show members inside the app. A null
 * organization means every organization sees it, which is why the model is not
 * scoped by BelongsToOrganization: the global scope would hide exactly those
 * rows. Visibility is the explicit `visibleIn()` query instead.
 *
 * @property-read string $id
 * @property-read string|null $organization_id
 * @property-read string $title
 * @property-read string $body
 * @property-read AnnouncementLevel $level
 * @property-read CarbonInterface $starts_at
 * @property-read CarbonInterface|null $ends_at
 * @property-read CarbonInterface $created_at
 * @property-read CarbonInterface $updated_at
 */
#[Unguarded]
final class Announcement extends Model
{
    /** @use HasFactory<AnnouncementFactory> */
    use HasFactory;

    use HasUuids;

    /**
     * @return BelongsTo<Organization, $this>
     */
    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    /**
     * @return BelongsToMany<User, $this>
     */
    public function dismissedBy(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'announcement_dismissals')->withPivot('dismissed_at');
    }

    /**
     * @return array<string, string>
     */
    public function casts(): array
    {
        return [
            'level' => AnnouncementLevel::class,
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
        ];
    }
}
