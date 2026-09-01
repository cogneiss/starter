<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\CreateWebhookEndpoint;
use App\Data\WebhookDeliveryData;
use App\Data\WebhookEndpointData;
use App\Http\Requests\StoreWebhookEndpointRequest;
use App\Http\Requests\UpdateWebhookEndpointRequest;
use App\Models\User;
use App\Models\WebhookDelivery;
use App\Models\WebhookEndpoint;
use App\Webhooks\WebhookEvents;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

final readonly class OrganizationWebhookEndpointController
{
    public function edit(WebhookEvents $events): Response
    {
        Gate::authorize('viewAny', WebhookEndpoint::class);

        return Inertia::render('organization/webhooks', [
            'endpoints' => WebhookEndpoint::query()
                ->latest()
                ->get()
                ->map(fn (WebhookEndpoint $endpoint): WebhookEndpointData => WebhookEndpointData::fromModel($endpoint)),
            'deliveries' => WebhookDelivery::query()
                ->latest()
                ->limit(50)
                ->get()
                ->map(fn (WebhookDelivery $delivery): WebhookDeliveryData => WebhookDeliveryData::fromModel($delivery)),
            'events' => $events->keys(),
        ]);
    }

    public function store(StoreWebhookEndpointRequest $request, #[CurrentUser] User $user, CreateWebhookEndpoint $action): RedirectResponse
    {
        Gate::authorize('create', WebhookEndpoint::class);

        /** @var list<string> $events */
        $events = $request->array('events');

        $created = $action->handle(
            $user,
            $request->string('url')->value(),
            $request->filled('description') ? $request->string('description')->value() : null,
            $events,
        );

        // The one and only place the plaintext secret exists: a flash the
        // redirect shows once. It is stored encrypted and never listed.
        Inertia::flash('webhookSecret', $created['secret']);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('Webhook endpoint created.'),
        ]);

        return to_route('webhook.edit');
    }

    public function update(UpdateWebhookEndpointRequest $request, WebhookEndpoint $endpoint): RedirectResponse
    {
        Gate::authorize('update', $endpoint);

        $endpoint->update($request->safe()->only(['url', 'description', 'events', 'active']));

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('Webhook endpoint updated.'),
        ]);

        return to_route('webhook.edit');
    }

    public function destroy(WebhookEndpoint $endpoint): RedirectResponse
    {
        Gate::authorize('delete', $endpoint);

        $endpoint->delete();

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('Webhook endpoint deleted.'),
        ]);

        return to_route('webhook.edit');
    }
}
