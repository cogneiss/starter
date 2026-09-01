<?php

declare(strict_types=1);

namespace App\Models;

use App\Concerns\BelongsToOrganization;
use Carbon\CarbonInterface;
use Database\Factories\WebhookEndpointFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A receiver an organization registered for outgoing webhooks. The signing
 * secret is stored encrypted, shown in plaintext exactly once at creation and
 * never returned by any endpoint afterwards.
 *
 * @property-read string $id
 * @property-read string $organization_id
 * @property-read string $url
 * @property-read string|null $description
 * @property-read list<string> $events
 * @property-read string $secret
 * @property-read bool $active
 * @property-read int $consecutive_failures
 * @property-read string|null $created_by
 * @property-read CarbonInterface $created_at
 * @property-read CarbonInterface $updated_at
 */
final class WebhookEndpoint extends Model
{
    use BelongsToOrganization;

    /** @use HasFactory<WebhookEndpointFactory> */
    use HasFactory;

    use HasUuids;

    protected $guarded = [];

    /**
     * Endpoints still receiving deliveries — matches the ApiToken::active() shape.
     *
     * @return Builder<self>
     */
    public static function active(): Builder
    {
        return self::query()->where('active', true);
    }

    /**
     * @return HasMany<WebhookDelivery, $this>
     */
    public function deliveries(): HasMany
    {
        return $this->hasMany(WebhookDelivery::class);
    }

    /**
     * @return array<string, string>
     */
    public function casts(): array
    {
        return [
            'events' => 'array',
            'secret' => 'encrypted',
            'active' => 'boolean',
            'consecutive_failures' => 'integer',
        ];
    }
}
