<?php

declare(strict_types=1);

use App\Models\Organization;
use App\Models\User;
use App\Resources\ResourceRegistry;
use App\Webhooks\WebhookEvents;

/**
 * The event catalogue and the resource registry cannot drift: every registered
 * resource emits exactly created/updated/deleted with its own data class as
 * payload, and nothing outside the registry is an event.
 */
beforeEach(function (): void {
    $this->organization = Organization::factory()->create();
    $this->owner = User::factory()->forOrganization($this->organization)->create();

    fakeWebhookDns();
});

it('derives the catalogue from the registry in both directions', function (): void {
    $expected = [];

    foreach (resolve(ResourceRegistry::class)->all() as $key => $resource) {
        foreach (['created', 'updated', 'deleted'] as $action) {
            $expected[$key.'.'.$action] = $resource->dataClass();
        }
    }

    $actual = resolve(WebhookEvents::class)->all();

    ksort($expected);
    ksort($actual);

    // Exact map equality both ways: an event with no registered resource, or
    // a resource with a missing event, each fails this line.
    expect($actual)->toBe($expected)
        ->and($expected)->not->toBe([]);
});

it('picks up a newly discovered resource with zero webhook code changes', function (): void {
    $key = withFakeResource();

    $events = resolve(WebhookEvents::class);

    expect($events->keys())->toContain($key.'.created', $key.'.updated', $key.'.deleted')
        ->and($events->has($key.'.created'))->toBeTrue();
});

it('offers exactly the catalogue as subscription options on the page', function (): void {
    $page = $this->actingAs($this->owner)
        ->get(route('webhook.edit'))
        ->assertOk()
        ->inertiaPage();

    expect($page['props']['events'])->toBe(resolve(WebhookEvents::class)->keys());
});

it('rejects a subscription to an event outside the catalogue', function (): void {
    $this->actingAs($this->owner)
        ->post(route('webhook.store'), [
            'url' => 'https://hooks.example.com/in',
            'events' => ['users.created', 'not-a-resource.created'],
        ])
        ->assertInvalid(['events.1']);
});
