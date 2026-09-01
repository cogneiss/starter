<?php

declare(strict_types=1);

namespace App\Models;

use App\Concerns\BelongsToOrganization;
use Carbon\CarbonInterface;
use Database\Factories\WebhookDeliveryFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One event sent (or attempted) to one endpoint. The stored response snippet
 * is scrubbed before it is written: neither the signing secret nor the
 * request's signature survives into the log, even when a receiver echoes them.
 *
 * @property-read string $id
 * @property-read string $organization_id
 * @property-read string $webhook_endpoint_id
 * @property-read string $event
 * @property-read array<string, mixed> $payload
 * @property-read int $attempt
 * @property-read string $status
 * @property-read int|null $status_code
 * @property-read string|null $response_snippet
 * @property-read int|null $duration_ms
 * @property-read CarbonInterface|null $next_attempt_at
 * @property-read CarbonInterface $created_at
 * @property-read CarbonInterface $updated_at
 */
final class WebhookDelivery extends Model
{
    use BelongsToOrganization;

    /** @use HasFactory<WebhookDeliveryFactory> */
    use HasFactory;

    use HasUuids;

    protected $guarded = [];

    /**
     * @return BelongsTo<WebhookEndpoint, $this>
     */
    public function endpoint(): BelongsTo
    {
        return $this->belongsTo(WebhookEndpoint::class, 'webhook_endpoint_id');
    }

    /**
     * @return array<string, string>
     */
    public function casts(): array
    {
        return [
            'payload' => 'array',
            'attempt' => 'integer',
            'status_code' => 'integer',
            'duration_ms' => 'integer',
            'next_attempt_at' => 'datetime',
        ];
    }
}
