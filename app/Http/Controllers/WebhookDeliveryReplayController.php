<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\ReplayWebhookDelivery;
use App\Models\WebhookDelivery;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;

final readonly class WebhookDeliveryReplayController
{
    public function __invoke(WebhookDelivery $delivery, ReplayWebhookDelivery $action): RedirectResponse
    {
        Gate::authorize('replay', $delivery);

        $action->handle($delivery);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('Delivery queued for replay.'),
        ]);

        return to_route('webhook.edit');
    }
}
