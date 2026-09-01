<?php

declare(strict_types=1);

use App\Models\User;
use App\Notifications\WebhookEndpointDeactivated;

it('is delivered to the in-app inbox alone', function (): void {
    expect(new WebhookEndpointDeactivated()->via(User::factory()->create()))
        ->toBe(['database']);
});

it('links back to the webhook settings page', function (): void {
    $payload = new WebhookEndpointDeactivated()->toDatabase(User::factory()->create());

    expect($payload)->toBe([
        'title' => __('Webhook endpoint deactivated after repeated failures.'),
        'url' => route('webhook.edit'),
    ]);
});
