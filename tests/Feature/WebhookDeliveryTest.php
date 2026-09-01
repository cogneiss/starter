<?php

declare(strict_types=1);

use App\Jobs\SendWebhookDelivery;
use App\Listeners\DispatchWebhookEvents;
use App\Models\Organization;
use App\Models\SavedSearch;
use App\Models\User;
use App\Models\WebhookDelivery;
use App\Models\WebhookEndpoint;
use App\Notifications\WebhookEndpointDeactivated;
use App\Support\OrganizationContext;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;

/**
 * The retry ladder lives on the delivery row: 30, 120, 480 and 1920 second
 * delays, a hard stop at attempt five, and endpoint deactivation after ten
 * consecutive final failures — every number from config, never inline.
 */
beforeEach(function (): void {
    $this->organization = Organization::factory()->create();
    $this->owner = User::factory()->forOrganization($this->organization)->create();
    $this->endpoint = WebhookEndpoint::factory()->create([
        'organization_id' => $this->organization->id,
        'url' => 'https://hooks.example.com/in',
        'secret' => 'whsec_delivery_secret',
    ]);

    fakeWebhookDns();
});

/**
 * Runs one attempt by hand — with the queue faked, dispatching would only
 * record the job, so the retry it schedules stays inspectable instead of
 * running its whole chain synchronously.
 */
function runWebhookAttempt(WebhookDelivery $delivery, Organization $organization): void
{
    resolve(OrganizationContext::class)->runAs($organization, function () use ($delivery, $organization): void {
        app()->call([new SendWebhookDelivery($delivery->id, $organization->id), 'handle']);
    });
}

it('marks a successful delivery sent and resets consecutive failures', function (): void {
    $this->endpoint->update(['consecutive_failures' => 3]);

    $delivery = WebhookDelivery::factory()->create([
        'organization_id' => $this->organization->id,
        'webhook_endpoint_id' => $this->endpoint->id,
    ]);

    Http::fake(fn () => Http::response('ok'));

    dispatch(new SendWebhookDelivery($delivery->id, $this->organization->id));

    $delivery->refresh();

    expect($delivery->status)->toBe('sent')
        ->and($delivery->attempt)->toBe(1)
        ->and($delivery->status_code)->toBe(200)
        ->and($delivery->next_attempt_at)->toBeNull()
        ->and($this->endpoint->refresh()->consecutive_failures)->toBe(0);
});

it('backs off at 30, 120, 480 and 1920 seconds and never makes a sixth attempt', function (): void {
    Queue::fake();
    Http::fake(fn () => Http::response('nope', 500));

    $delivery = WebhookDelivery::factory()->create([
        'organization_id' => $this->organization->id,
        'webhook_endpoint_id' => $this->endpoint->id,
    ]);

    foreach (range(1, 5) as $attempt) {
        runWebhookAttempt($delivery, $this->organization);

        expect($delivery->refresh()->attempt)->toBe($attempt);
    }

    $delays = Queue::pushed(SendWebhookDelivery::class)->map(fn (SendWebhookDelivery $job) => $job->delay)->all();

    expect($delays)->toBe([30, 120, 480, 1920])
        ->and($delivery->status)->toBe('failed')
        ->and($this->endpoint->refresh()->consecutive_failures)->toBe(1);

    // A sixth run is a no-op: the row already carries the final attempt.
    runWebhookAttempt($delivery, $this->organization);

    expect($delivery->refresh()->attempt)->toBe(5);
    expect(Queue::pushed(SendWebhookDelivery::class)->count())->toBe(4);
    Http::assertSentCount(5);
});

it('records next_attempt_at from the backoff delay', function (): void {
    Queue::fake();
    Http::fake(fn () => Http::response('nope', 500));

    $delivery = WebhookDelivery::factory()->create([
        'organization_id' => $this->organization->id,
        'webhook_endpoint_id' => $this->endpoint->id,
    ]);

    runWebhookAttempt($delivery, $this->organization);

    expect($delivery->refresh()->next_attempt_at?->getTimestamp())->toBe(now()->addSeconds(30)->getTimestamp());
});

it('keeps the endpoint active through nine consecutive final failures and deactivates on the tenth', function (): void {
    Notification::fake();
    Http::fake(fn () => Http::response('nope', 500));

    $this->endpoint->update(['consecutive_failures' => 8]);

    $delivery = WebhookDelivery::factory()->create([
        'organization_id' => $this->organization->id,
        'webhook_endpoint_id' => $this->endpoint->id,
        'attempt' => 4,
    ]);

    dispatch(new SendWebhookDelivery($delivery->id, $this->organization->id));

    expect($this->endpoint->refresh()->consecutive_failures)->toBe(9)
        ->and($this->endpoint->active)->toBeTrue();
    Notification::assertNothingSent();

    $tenth = WebhookDelivery::factory()->create([
        'organization_id' => $this->organization->id,
        'webhook_endpoint_id' => $this->endpoint->id,
        'attempt' => 4,
    ]);

    dispatch(new SendWebhookDelivery($tenth->id, $this->organization->id));

    expect($this->endpoint->refresh()->consecutive_failures)->toBe(10)
        ->and($this->endpoint->active)->toBeFalse();
    Notification::assertSentTo($this->owner, WebhookEndpointDeactivated::class);
});

it('reads the attempt cap from config', function (): void {
    config(['webhooks.max_attempts' => 2]);

    Queue::fake();
    Http::fake(fn () => Http::response('nope', 500));

    $delivery = WebhookDelivery::factory()->create([
        'organization_id' => $this->organization->id,
        'webhook_endpoint_id' => $this->endpoint->id,
    ]);

    runWebhookAttempt($delivery, $this->organization);
    runWebhookAttempt($delivery, $this->organization);

    expect(Queue::pushed(SendWebhookDelivery::class)->count())->toBe(1)
        ->and($delivery->refresh()->attempt)->toBe(2)
        ->and($this->endpoint->refresh()->consecutive_failures)->toBe(1);
});

it('reads the deactivation threshold from config', function (): void {
    config(['webhooks.deactivate_after' => 1]);

    Notification::fake();
    Http::fake(fn () => Http::response('nope', 500));

    $delivery = WebhookDelivery::factory()->create([
        'organization_id' => $this->organization->id,
        'webhook_endpoint_id' => $this->endpoint->id,
        'attempt' => 4,
    ]);

    dispatch(new SendWebhookDelivery($delivery->id, $this->organization->id));

    expect($this->endpoint->refresh()->active)->toBeFalse();
    Notification::assertSentTo($this->owner, WebhookEndpointDeactivated::class);
});

it('replay re-sends and logs a new attempt as its own row', function (): void {
    $original = WebhookDelivery::factory()->create([
        'organization_id' => $this->organization->id,
        'webhook_endpoint_id' => $this->endpoint->id,
        'attempt' => 5,
        'status' => 'failed',
    ]);

    Http::fake(fn () => Http::response('ok'));

    $this->actingAs($this->owner)
        ->post(route('webhook.replay', $original))
        ->assertRedirect(route('webhook.edit'));

    $replay = WebhookDelivery::withoutOrganizationScope()->whereKeyNot($original->id)->sole();

    expect($replay->status)->toBe('sent')
        ->and($replay->attempt)->toBe(1)
        ->and($replay->event)->toBe($original->event)
        ->and($replay->payload)->toBe($original->payload)
        ->and($original->refresh()->attempt)->toBe(5);
});

it('scrubs the secret and the signature from the stored response snippet', function (): void {
    $delivery = WebhookDelivery::factory()->create([
        'organization_id' => $this->organization->id,
        'webhook_endpoint_id' => $this->endpoint->id,
    ]);

    // A hostile receiver echoing both the signing secret and the request's
    // own signature back in its response body.
    Http::fake(fn ($request) => Http::response(
        'whsec_delivery_secret and '.$request->header('X-Signature')[0].' echoed',
    ));

    dispatch(new SendWebhookDelivery($delivery->id, $this->organization->id));

    $snippet = (string) $delivery->refresh()->response_snippet;

    expect($snippet)->toContain('[redacted]')
        ->and($snippet)->not->toContain('whsec_delivery_secret')
        ->and(preg_match('/\b[0-9a-f]{64}\b/', $snippet))->toBe(0);
});

it('records a connection failure with no status code and retries', function (): void {
    Queue::fake();
    Http::fake(function (): void {
        throw new ConnectionException('could not connect');
    });

    $delivery = WebhookDelivery::factory()->create([
        'organization_id' => $this->organization->id,
        'webhook_endpoint_id' => $this->endpoint->id,
    ]);

    runWebhookAttempt($delivery, $this->organization);

    $delivery->refresh();

    expect($delivery->status)->toBe('failed')
        ->and($delivery->status_code)->toBeNull()
        ->and($delivery->response_snippet)->toBeNull();
    Queue::assertPushed(SendWebhookDelivery::class, 1);
});

it('skips a delivery whose endpoint was deactivated meanwhile', function (): void {
    $this->endpoint->update(['active' => false]);

    $delivery = WebhookDelivery::factory()->create([
        'organization_id' => $this->organization->id,
        'webhook_endpoint_id' => $this->endpoint->id,
    ]);

    Http::fake();

    dispatch(new SendWebhookDelivery($delivery->id, $this->organization->id));

    expect($delivery->refresh()->status)->toBe('pending');
    Http::assertNothingSent();
});

it('quietly ignores a delivery that no longer exists', function (): void {
    Http::fake();

    dispatch(new SendWebhookDelivery(Str::uuid()->toString(), $this->organization->id));

    Http::assertNothingSent();
});

it('exposes its attempts through the endpoint relation', function (): void {
    $delivery = WebhookDelivery::factory()->create([
        'organization_id' => $this->organization->id,
        'webhook_endpoint_id' => $this->endpoint->id,
    ]);

    resolve(OrganizationContext::class)->runAs($this->organization, function () use ($delivery): void {
        expect($this->endpoint->deliveries()->pluck('id')->all())->toBe([$delivery->id]);
    });
});

it('dispatches nothing for a model outside the resource registry', function (): void {
    resolve(OrganizationContext::class)->runAs($this->organization, function (): void {
        SavedSearch::factory()->create([
            'organization_id' => $this->organization->id,
            'user_id' => $this->owner->id,
        ]);
    });

    expect(WebhookDelivery::withoutOrganizationScope()->count())->toBe(0);
});

it('ignores an eloquent action outside the event catalogue', function (): void {
    resolve(OrganizationContext::class)->runAs($this->organization, function (): void {
        resolve(DispatchWebhookEvents::class)->handle('eloquent.restored: App\Models\User', [$this->owner]);
    });

    expect(WebhookDelivery::withoutOrganizationScope()->count())->toBe(0);
});
