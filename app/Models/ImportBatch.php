<?php

declare(strict_types=1);

namespace App\Models;

use App\Concerns\BelongsToOrganization;
use Carbon\CarbonInterface;
use Database\Factories\ImportBatchFactory;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * One run of one import.
 *
 * @property-read string $id
 * @property-read string $organization_id
 * @property-read string $user_id
 * @property string|null $temp_upload_id
 * @property string $import
 * @property string $status
 * @property-read CarbonInterface $created_at
 * @property-read CarbonInterface $updated_at
 * @property-read Organization $organization
 * @property-read TempUpload|null $tempUpload
 * @property-read Collection<int, ImportRow> $rows
 */
#[Fillable([
    'user_id',
    'temp_upload_id',
    'import',
    'status',
])]
final class ImportBatch extends Model
{
    use BelongsToOrganization;

    /** @use HasFactory<ImportBatchFactory> */
    use HasFactory;

    use HasUuids;

    /**
     * Every read of a batch starts here, for the same reason
     * {@see TempUpload::ownedBy()} does: somebody else's run is not found rather
     * than refused.
     *
     * @return Builder<self>
     */
    public static function ownedBy(?Authenticatable $user): Builder
    {
        return self::query()->where('user_id', $user?->getAuthIdentifier());
    }

    /**
     * @return HasMany<ImportRow, $this>
     */
    public function rows(): HasMany
    {
        return $this->hasMany(ImportRow::class);
    }

    /**
     * @return BelongsTo<TempUpload, $this>
     */
    public function tempUpload(): BelongsTo
    {
        return $this->belongsTo(TempUpload::class);
    }

    /**
     * @return array<string, string>
     */
    public function casts(): array
    {
        return [
            'id' => 'string',
            'organization_id' => 'string',
            'user_id' => 'string',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }
}
