<?php

declare(strict_types=1);

namespace App\Models;

use App\Concerns\BelongsToOrganization;
use App\Enums\AiAuditStatus;
use Carbon\CarbonInterface;
use Database\Factories\AiAuditLogFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

/**
 * One agent invocation, recorded whether it reached a provider or not. Quota
 * counting, spend reporting and abuse review all read this table, so it is
 * append-only: no updated_at column, and update()/delete() refuse.
 *
 * @property-read string $id
 * @property-read string $organization_id
 * @property-read string|null $user_id
 * @property-read string $agent
 * @property-read string|null $provider
 * @property-read string|null $model
 * @property-read string|null $tier
 * @property-read int $prompt_tokens
 * @property-read int $completion_tokens
 * @property-read int $total_tokens
 * @property-read int $cost_micros
 * @property-read int $duration_ms
 * @property-read AiAuditStatus $status
 * @property-read string|null $blocked_reason
 * @property-read array<int, mixed>|null $tool_calls
 * @property-read CarbonInterface $created_at
 * @property-read Organization $organization
 * @property-read User|null $user
 */
final class AiAuditLog extends Model
{
    use BelongsToOrganization;

    /** @use HasFactory<AiAuditLogFactory> */
    use HasFactory;

    use HasUuids;

    public const UPDATED_AT = null;

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * The row describes something that already happened. Correcting it means
     * writing another row, never editing this one.
     *
     * @param  array<string, mixed>  $attributes
     * @param  array<string, mixed>  $options
     */
    public function update(array $attributes = [], array $options = []): bool
    {
        throw new LogicException('ai_audit_logs is append-only — write a new row instead of updating one.');
    }

    public function delete(): bool
    {
        throw new LogicException('ai_audit_logs is append-only — rows are never deleted.');
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
            'prompt_tokens' => 'integer',
            'completion_tokens' => 'integer',
            'total_tokens' => 'integer',
            'cost_micros' => 'integer',
            'duration_ms' => 'integer',
            'status' => AiAuditStatus::class,
            'tool_calls' => 'array',
            'created_at' => 'datetime',
        ];
    }
}
