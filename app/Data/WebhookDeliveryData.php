<?php

declare(strict_types=1);

namespace App\Data;

use App\Models\WebhookDelivery;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript('WebhookDelivery')]
final class WebhookDeliveryData extends Data
{
    public function __construct(
        public string $id,
        public string $endpointId,
        public string $event,
        public int $attempt,
        public string $status,
        public ?int $statusCode,
        public ?int $durationMs,
        public ?string $nextAttemptAt,
        public string $createdAt,
    ) {}

    public static function fromModel(WebhookDelivery $delivery): self
    {
        return new self(
            id: $delivery->id,
            endpointId: $delivery->webhook_endpoint_id,
            event: $delivery->event,
            attempt: $delivery->attempt,
            status: $delivery->status,
            statusCode: $delivery->status_code,
            durationMs: $delivery->duration_ms,
            nextAttemptAt: $delivery->next_attempt_at?->toIso8601String(),
            createdAt: $delivery->created_at->toIso8601String(),
        );
    }
}
