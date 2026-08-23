<?php

declare(strict_types=1);

namespace App\Models;

use Carbon\CarbonInterface;
use Database\Factories\SocialAccountFactory;
use Illuminate\Database\Eloquent\Attributes\WithoutTimestamps;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One identity a user holds with an OAuth provider. The unique index on
 * provider and provider_user_id is what makes a repeated login idempotent.
 *
 * @property-read string $id
 * @property-read string $user_id
 * @property-read string $provider
 * @property-read string $provider_user_id
 * @property-read CarbonInterface|null $created_at
 * @property-read User $user
 */
#[WithoutTimestamps]
final class SocialAccount extends Model
{
    /** @use HasFactory<SocialAccountFactory> */
    use HasFactory;

    use HasUuids;

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return array<string, string>
     */
    public function casts(): array
    {
        return [
            'id' => 'string',
            'user_id' => 'string',
            'created_at' => 'datetime',
        ];
    }
}
