<?php

declare(strict_types=1);

namespace App\Actions;

use App\Jobs\SendWebhookDelivery;
use App\Models\WebhookDelivery;

final readonly class ReplayWebhookDelivery
{
    /**
     * A replay is a new delivery row, not a mutation of the old one: the
     * original's attempts and outcome stay in the log, and the new row gets
     * its own attempt count starting at zero.
     */
    public function handle(WebhookDelivery $delivery): WebhookDelivery
    {
        $replay = WebhookDelivery::query()->create([
            'webhook_endpoint_id' => $delivery->webhook_endpoint_id,
            'event' => $delivery->event,
            'payload' => $delivery->payload,
            'status' => 'pending',
        ]);

        SendWebhookDelivery::dispatch($replay->id, $delivery->organization_id)->afterCommit();

        return $replay;
    }
}
