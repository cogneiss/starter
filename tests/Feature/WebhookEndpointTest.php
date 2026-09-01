<?php

declare(strict_types=1);

use App\Models\Organization;
use App\Models\User;
use App\Models\WebhookDelivery;
use App\Models\WebhookEndpoint;

/**
 * The signing secret exists in plaintext exactly once — in the flash of the
 * creating request — and every webhook route resolves its records inside the
 * organization's own query, so a foreign id is a 404.
 */
beforeEach(function (): void {
    $this->organization = Organization::factory()->create();
    $this->owner = User::factory()->forOrganization($this->organization)->create();

    fakeWebhookDns();
});

it('flashes the secret once at creation and stores it encrypted', function (): void {
    $this->actingAs($this->owner)
        ->post(route('webhook.store'), [
            'url' => 'https://hooks.example.com/in',
            'description' => 'Reporting integration',
            'events' => ['users.created'],
        ])
        ->assertRedirect(route('webhook.edit'));

    $flash = session('inertia.flash_data');
    $secret = $flash['webhookSecret'] ?? null;

    expect($secret)->toBeString()->toStartWith('whsec_');

    expect(mb_substr_count(json_encode($flash), $secret))->toBe(1);

    $endpoint = WebhookEndpoint::withoutOrganizationScope()->sole();

    expect($endpoint->getRawOriginal('secret'))->not->toContain($secret)
        ->and($endpoint->secret)->toBe($secret);
});

it('never carries the secret in the page props', function (): void {
    $this->actingAs($this->owner)->post(route('webhook.store'), [
        'url' => 'https://hooks.example.com/in',
        'events' => ['users.created'],
    ]);

    $secret = session('inertia.flash_data')['webhookSecret'];

    // The redirect target consumes the one-shot flash; the page itself must
    // never carry the plaintext again.
    $this->actingAs($this->owner)->get(route('webhook.edit'));

    $page = $this->actingAs($this->owner)
        ->get(route('webhook.edit'))
        ->assertOk()
        ->inertiaPage();

    expect(mb_substr_count(json_encode($page), $secret))->toBe(0);
});

it('renders the endpoints, recent deliveries and event catalogue', function (): void {
    $endpoint = WebhookEndpoint::factory()->create(['organization_id' => $this->organization->id]);
    WebhookDelivery::factory()->create([
        'organization_id' => $this->organization->id,
        'webhook_endpoint_id' => $endpoint->id,
    ]);

    $this->actingAs($this->owner)
        ->get(route('webhook.edit'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('organization/webhooks')
            ->count('endpoints', 1)
            ->count('deliveries', 1)
            ->where('endpoints.0.id', $endpoint->id)
            ->has('events'));
});

it('updates an endpoint and reactivates a deactivated one', function (): void {
    $endpoint = WebhookEndpoint::factory()->inactive()->create([
        'organization_id' => $this->organization->id,
        'consecutive_failures' => 10,
    ]);

    $this->actingAs($this->owner)
        ->patch(route('webhook.update', $endpoint), ['active' => true])
        ->assertRedirect(route('webhook.edit'));

    expect($endpoint->refresh()->active)->toBeTrue();
});

it('deletes an endpoint', function (): void {
    $endpoint = WebhookEndpoint::factory()->create(['organization_id' => $this->organization->id]);

    $this->actingAs($this->owner)
        ->delete(route('webhook.destroy', $endpoint))
        ->assertRedirect(route('webhook.edit'));

    expect(WebhookEndpoint::withoutOrganizationScope()->count())->toBe(0);
});

it('lets a read-only member view but not manage', function (): void {
    $member = User::factory()->forOrganization($this->organization, 'Member')->create();
    $endpoint = WebhookEndpoint::factory()->create(['organization_id' => $this->organization->id]);

    $this->actingAs($member)->get(route('webhook.edit'))->assertOk();

    $this->actingAs($member)->post(route('webhook.store'), [
        'url' => 'https://hooks.example.com/in',
        'events' => ['users.created'],
    ])->assertForbidden();

    $this->actingAs($member)->patch(route('webhook.update', $endpoint), ['active' => false])->assertForbidden();
    $this->actingAs($member)->delete(route('webhook.destroy', $endpoint))->assertForbidden();
});

it('returns 404 for a foreign endpoint id on update and delete', function (): void {
    $foreign = WebhookEndpoint::factory()->create();

    $this->actingAs($this->owner)
        ->patch(route('webhook.update', $foreign->id), ['active' => false])
        ->assertNotFound();

    $this->actingAs($this->owner)
        ->delete(route('webhook.destroy', $foreign->id))
        ->assertNotFound();

    expect(WebhookEndpoint::withoutOrganizationScope()->count())->toBe(1);
});

it('returns 404 for a foreign delivery id on replay', function (): void {
    $foreign = WebhookDelivery::factory()->create();

    $this->actingAs($this->owner)
        ->post(route('webhook.replay', $foreign->id))
        ->assertNotFound();

    expect(WebhookDelivery::withoutOrganizationScope()->count())->toBe(1);
});
