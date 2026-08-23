<?php

declare(strict_types=1);

namespace App\Models;

use Carbon\CarbonInterface;
use Database\Factories\LoginHistoryFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\MassPrunable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One sign-in attempt, successful or not. Append only: a row is written once
 * and never touched again, which is why there is no `updated_at`.
 *
 * A failed attempt on an address nobody owns still gets a row, with a null
 * `user_id` — that pattern is the interesting one.
 *
 * @property-read string $id
 * @property-read string|null $user_id
 * @property-read string $email
 * @property-read string|null $ip_address
 * @property-read string|null $user_agent
 * @property-read bool $successful
 * @property-read CarbonInterface $created_at
 * @property-read User|null $user
 */
final class LoginHistory extends Model
{
    /** @use HasFactory<LoginHistoryFactory> */
    use HasFactory;

    use HasUuids;
    use MassPrunable;

    public const ?string UPDATED_AT = null;

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return Builder<self>
     */
    public function prunable(): Builder
    {
        return self::query()->where('created_at', '<', now()->subDays(90));
    }

    /**
     * @return array<string, string>
     */
    public function casts(): array
    {
        return [
            'id' => 'string',
            'user_id' => 'string',
            'successful' => 'boolean',
            'created_at' => 'datetime',
        ];
    }
}
