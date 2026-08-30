<?php

declare(strict_types=1);

namespace App\Models;

use App\Concerns\BelongsToOrganization;
use Carbon\CarbonInterface;
use Database\Factories\TempUploadFactory;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * A file somebody uploaded that nothing trusts yet.
 *
 * @property-read string $id
 * @property-read string $organization_id
 * @property-read string $user_id
 * @property string $disk
 * @property string $path
 * @property string $original_name
 * @property string $mime
 * @property int $size
 * @property CarbonInterface|null $scanned_at
 * @property string|null $scan_result
 * @property CarbonInterface|null $promoted_at
 * @property CarbonInterface $expires_at
 * @property-read CarbonInterface $created_at
 * @property-read CarbonInterface $updated_at
 * @property-read Organization $organization
 */
#[Fillable([
    'user_id',
    'disk',
    'path',
    'original_name',
    'mime',
    'size',
    'expires_at',
])]
final class TempUpload extends Model
{
    use BelongsToOrganization;

    /** @use HasFactory<TempUploadFactory> */
    use HasFactory;

    use HasUuids;

    /**
     * Every read of an upload starts here.
     *
     * The organization arrives from the global scope and the person from the
     * predicate, so an upload belonging to somebody else is absent from the
     * result rather than fetched and then refused.
     *
     * @return Builder<self>
     */
    public static function ownedBy(?Authenticatable $user): Builder
    {
        return self::query()->where('user_id', $user?->getAuthIdentifier());
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
            'size' => 'integer',
            'scanned_at' => 'datetime',
            'promoted_at' => 'datetime',
            'expires_at' => 'datetime',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }
}
