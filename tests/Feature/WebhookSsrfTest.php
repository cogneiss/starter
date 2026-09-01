<?php

declare(strict_types=1);

use App\Jobs\SendWebhookDelivery;
use App\Models\Organization;
use App\Models\User;
use App\Models\WebhookDelivery;
use App\Models\WebhookEndpoint;
use App\Support\OrganizationContext;
use App\Webhooks\ResolvesHostnames;
use App\Webhooks\SsrfGuard;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;

/**
 * The range check runs twice — at validation and again immediately before
 * every send — and the connection is pinned to the exact address that passed,
 * so a DNS answer that changes in between cannot steer a request inward.
 */
beforeEach(function (): void {
    $this->organization = Organization::factory()->create();
    $this->owner = User::factory()->forOrganization($this->organization)->create();
});

it('validation refuses urls that are not public https', function (string $url, array $dns): void {
    fakeWebhookDns($dns);

    $this->actingAs($this->owner)
        ->post(route('webhook.store'), ['url' => $url, 'events' => ['users.created']])
        ->assertInvalid(['url']);

    expect(WebhookEndpoint::withoutOrganizationScope()->count())->toBe(0);
})->with([
    'plain http' => ['http://hooks.example.com/in', []],
    'loopback literal' => ['https://127.0.0.1/in', ['127.0.0.1' => ['127.0.0.1']]],
    'private range' => ['https://internal.example.com/in', ['internal.example.com' => ['10.0.0.8']]],
    'link-local metadata' => ['https://metadata.example.com/in', ['metadata.example.com' => ['169.254.169.254']]],
    'one private record among public' => ['https://mixed.example.com/in', ['mixed.example.com' => ['93.184.216.34', '192.168.1.5']]],
    'hostname that does not resolve' => ['https://dead.example.com/in', ['dead.example.com' => []]],
]);

it('validation accepts a public https url', function (): void {
    fakeWebhookDns();

    $this->actingAs($this->owner)
        ->post(route('webhook.store'), ['url' => 'https://hooks.example.com/in', 'events' => ['users.created']])
        ->assertValid()
        ->assertRedirect(route('webhook.edit'));
});

it('re-checks dns at send time and blocks an answer that turned private', function (): void {
    $resolver = new class implements ResolvesHostnames
    {
        public bool $private = false;

        public function resolve(string $hostname): array
        {
            return $this->private ? ['10.0.0.8'] : ['93.184.216.34'];
        }
    };

    app()->instance(ResolvesHostnames::class, $resolver);

    // Public at validation: the endpoint saves.
    $this->actingAs($this->owner)
        ->post(route('webhook.store'), ['url' => 'https://hooks.example.com/in', 'events' => ['users.created']])
        ->assertValid();

    $endpoint = WebhookEndpoint::withoutOrganizationScope()->sole();
    $delivery = WebhookDelivery::factory()->create([
        'organization_id' => $this->organization->id,
        'webhook_endpoint_id' => $endpoint->id,
    ]);

    // Private at send: the rebound answer is refused before any connection.
    $resolver->private = true;

    Http::fake();

    dispatch(new SendWebhookDelivery($delivery->id, $this->organization->id));

    expect($delivery->refresh()->status)->toBe('blocked')
        ->and($endpoint->refresh()->consecutive_failures)->toBe(1);
    Http::assertNothingSent();
});

it('pins the connection to the checked ip and disables redirects', function (): void {
    fakeWebhookDns(['hooks.example.com' => ['93.184.216.34', '93.184.216.35']]);

    $endpoint = WebhookEndpoint::factory()->create([
        'organization_id' => $this->organization->id,
        'url' => 'https://hooks.example.com/in',
    ]);
    $delivery = WebhookDelivery::factory()->create([
        'organization_id' => $this->organization->id,
        'webhook_endpoint_id' => $endpoint->id,
    ]);

    Http::fake(function ($request, array $options) {
        // The transfer options are only visible here: the connection is
        // pinned to the first checked address and redirects are off.
        expect($options['curl'][CURLOPT_RESOLVE])->toBe(['hooks.example.com:443:93.184.216.34'])
            ->and($options['allow_redirects'])->toBeFalse();

        return Http::response('ok');
    });

    dispatch(new SendWebhookDelivery($delivery->id, $this->organization->id));

    expect($delivery->refresh()->status)->toBe('sent');
    Http::assertSentCount(1);
});

it('does not follow a redirect toward a private host and records the 3xx', function (): void {
    fakeWebhookDns();

    $endpoint = WebhookEndpoint::factory()->create([
        'organization_id' => $this->organization->id,
        'url' => 'https://hooks.example.com/in',
    ]);
    $delivery = WebhookDelivery::factory()->create([
        'organization_id' => $this->organization->id,
        'webhook_endpoint_id' => $endpoint->id,
    ]);

    Queue::fake();
    Http::fake(fn () => Http::response('', 302, ['Location' => 'http://169.254.169.254/latest/meta-data']));

    resolve(OrganizationContext::class)->runAs($this->organization, function () use ($delivery): void {
        app()->call([new SendWebhookDelivery($delivery->id, $this->organization->id), 'handle']);
    });

    // One request total: the redirect was recorded as a failure, not chased.
    Http::assertSentCount(1);

    expect($delivery->refresh()->status)->toBe('failed')
        ->and($delivery->status_code)->toBe(302);
});

it('blocks a saved url with no usable hostname as a final failure', function (): void {
    fakeWebhookDns();

    $endpoint = WebhookEndpoint::factory()->create([
        'organization_id' => $this->organization->id,
        'url' => 'https:',
    ]);
    $delivery = WebhookDelivery::factory()->create([
        'organization_id' => $this->organization->id,
        'webhook_endpoint_id' => $endpoint->id,
    ]);

    Http::fake();

    dispatch(new SendWebhookDelivery($delivery->id, $this->organization->id));

    expect($delivery->refresh()->status)->toBe('blocked')
        ->and($endpoint->refresh()->consecutive_failures)->toBe(1);
    Http::assertNothingSent();
});

it('classifies private and reserved ranges as non-public', function (): void {
    fakeWebhookDns();

    $guard = resolve(SsrfGuard::class);

    expect($guard->isPublicIp('93.184.216.34'))->toBeTrue()
        ->and($guard->isPublicIp('2606:2800:220:1:248:1893:25c8:1946'))->toBeTrue();

    foreach (['127.0.0.1', '10.0.0.8', '172.16.4.2', '192.168.1.5', '169.254.169.254', '0.0.0.0', '::1', 'fc00::1', 'fe80::1', 'not-an-ip'] as $refused) {
        expect($guard->isPublicIp($refused))->toBeFalse();
    }

    expect($guard->allows('https://hooks.example.com/in'))->toBeTrue()
        ->and($guard->allows('ftp://hooks.example.com/in'))->toBeFalse()
        ->and($guard->allows('https:'))->toBeFalse();
});
