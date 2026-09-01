<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Jobs\SendWebhookDelivery;
use App\Models\WebhookDelivery;
use App\Models\WebhookEndpoint;
use App\Resources\ResourceRegistry;
use App\Support\OrganizationContext;
use App\Webhooks\WebhookEvents;
use Illuminate\Database\Eloquent\Model;

/**
 * Wildcard eloquent listener that turns changes to registered resources into
 * webhook deliveries. Only models the resource registry knows about emit
 * events, so internal bookkeeping tables (deliveries included) never trigger
 * webhooks about themselves.
 */
final readonly class DispatchWebhookEvents
{
    public function __construct(
        private WebhookEvents $events,
        private OrganizationContext $context,
    ) {}

    /**
     * @param  array{0: Model}  $payload
     */
    public function handle(string $event, array $payload): void
    {
        $model = $payload[0];

        $organizationId = $this->context->id();

        if ($organizationId === null) {
            return;
        }

        $active = WebhookEndpoint::active()->get();

        if ($active->isEmpty()) {
            return;
        }

        $definition = resolve(ResourceRegistry::class)->forModel($model);

        if ($definition === null) {
            return;
        }

        $action = str($event)->between('eloquent.', ':')->value();
        $name = $definition->key().'.'.$action;

        if (! $this->events->has($name)) {
            return;
        }

        $endpoints = $active->filter(fn (WebhookEndpoint $endpoint): bool => in_array($name, $endpoint->events, true));

        foreach ($endpoints as $endpoint) {
            $delivery = WebhookDelivery::query()->create([
                'webhook_endpoint_id' => $endpoint->id,
                'event' => $name,
                'payload' => [
                    'event' => $name,
                    'data' => $definition->dataClass()::from($model)->toArray(),
                ],
                'status' => 'pending',
            ]);

            SendWebhookDelivery::dispatch($delivery->id, $organizationId)->afterCommit();
        }
    }
}
