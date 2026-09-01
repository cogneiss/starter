<?php

declare(strict_types=1);

namespace App\Data;

use App\Models\WebhookEndpoint;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * An endpoint as the settings list shows it. The signing secret is not here
 * and never will be: it exists only in the flash message of the request that
 * created the endpoint.
 */
#[TypeScript('WebhookEndpoint')]
final class WebhookEndpointData extends Data
{
    /**
     * @param  list<string>  $events
     */
    public function __construct(
        public string $id,
        public string $url,
        public ?string $description,
        public array $events,
        public bool $active,
        public int $consecutiveFailures,
        public string $createdAt,
    ) {}

    public static function fromModel(WebhookEndpoint $endpoint): self
    {
        return new self(
            id: $endpoint->id,
            url: $endpoint->url,
            description: $endpoint->description,
            events: $endpoint->events,
            active: $endpoint->active,
            consecutiveFailures: $endpoint->consecutive_failures,
            createdAt: $endpoint->created_at->toIso8601String(),
        );
    }
}
