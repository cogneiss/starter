<?php

declare(strict_types=1);

namespace App\Models;

use App\Concerns\BelongsToOrganization;
use Carbon\CarbonInterface;
use Database\Factories\AiConfirmTokenFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Crypt;

/**
 * One proposed write, waiting for the person to confirm it.
 *
 * The payload is encrypted at rest and signed, so a row edited in the database
 * between propose and consume no longer verifies. The signature covers the id
 * too: a payload cannot be lifted from one token onto another.
 *
 * @property-read string $id
 * @property-read string $organization_id
 * @property-read string $user_id
 * @property-read string $action
 * @property-read array<string, mixed> $payload
 * @property-read string $signature
 * @property-read string $summary
 * @property-read CarbonInterface $expires_at
 * @property-read CarbonInterface|null $consumed_at
 * @property-read CarbonInterface $created_at
 * @property-read Organization $organization
 * @property-read User $user
 */
final class AiConfirmToken extends Model
{
    use BelongsToOrganization;

    /** @use HasFactory<AiConfirmTokenFactory> */
    use HasFactory;

    use HasUuids;

    public const UPDATED_AT = null;

    /**
     * The signature covers the token identity, the action key and the payload.
     * Anything the consume step trusts is inside it.
     *
     * @param  array<string, mixed>  $payload
     */
    public static function signatureFor(string $id, string $action, array $payload): string
    {
        return hash_hmac(
            'sha256',
            json_encode([$id, $action, $payload], JSON_THROW_ON_ERROR),
            Crypt::getKey(),
        );
    }

    public function hasValidSignature(): bool
    {
        return hash_equals(
            self::signatureFor($this->id, $this->action, $this->payload),
            $this->signature,
        );
    }

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
            'organization_id' => 'string',
            'user_id' => 'string',
            'payload' => 'encrypted:array',
            'expires_at' => 'datetime',
            'consumed_at' => 'datetime',
            'created_at' => 'datetime',
        ];
    }
}
