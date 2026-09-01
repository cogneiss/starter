<?php

declare(strict_types=1);

namespace App\Models;

use App\Concerns\BelongsToOrganization;
use Carbon\CarbonInterface;
use Database\Factories\ApiRequestLogFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

/**
 * One authenticated API request, recorded by the terminable logging middleware.
 * Carries routing facts only — never a body, query value or header — and is
 * append-only: no updated_at column, and update()/delete() refuse.
 *
 * @property-read string $id
 * @property-read string $organization_id
 * @property-read string|null $api_token_id
 * @property-read string $method
 * @property-read string $path
 * @property-read string|null $resource
 * @property-read int $status
 * @property-read int $duration_ms
 * @property-read CarbonInterface $created_at
 * @property-read Organization $organization
 * @property-read ApiToken|null $token
 */
final class ApiRequestLog extends Model
{
    use BelongsToOrganization;

    /** @use HasFactory<ApiRequestLogFactory> */
    use HasFactory;

    use HasUuids;

    public const UPDATED_AT = null;

    /**
     * @return BelongsTo<ApiToken, $this>
     */
    public function token(): BelongsTo
    {
        return $this->belongsTo(ApiToken::class, 'api_token_id');
    }

    /**
     * The row describes a request that already happened. Correcting it means
     * writing another row, never editing this one.
     *
     * @param  array<string, mixed>  $attributes
     * @param  array<string, mixed>  $options
     */
    public function update(array $attributes = [], array $options = []): bool
    {
        throw new LogicException('api_request_logs is append-only — write a new row instead of updating one.');
    }

    public function delete(): bool
    {
        throw new LogicException('api_request_logs is append-only — rows are pruned by api:prune-logs, never deleted one by one.');
    }

    /**
     * @return array<string, string>
     */
    public function casts(): array
    {
        return [
            'id' => 'string',
            'organization_id' => 'string',
            'api_token_id' => 'string',
            'status' => 'integer',
            'duration_ms' => 'integer',
            'created_at' => 'datetime',
        ];
    }
}
