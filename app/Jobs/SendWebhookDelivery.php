<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Contracts\OrganizationAware;
use App\Models\Role;
use App\Models\WebhookDelivery;
use App\Models\WebhookEndpoint;
use App\Notifications\WebhookEndpointDeactivated;
use App\Queue\Middleware\WithOrganizationContext;
use App\Webhooks\ResolvesHostnames;
use App\Webhooks\Signature;
use App\Webhooks\SsrfGuard;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;

/**
 * One delivery attempt. Retries re-dispatch this job with a delay rather than
 * releasing it, so the attempt count lives on the delivery row and stops hard
 * at the configured cap — attempt five is the last, there is no sixth.
 *
 * The URL's hostname is resolved again here, immediately before the send, and
 * every resolved address must sit in a public range. The connection is then
 * pinned to the exact address that passed the check, so a DNS answer that
 * changes between validation and connect cannot steer the request inward.
 */
final class SendWebhookDelivery implements OrganizationAware, ShouldQueue
{
    use Queueable;

    public function __construct(
        private readonly string $deliveryId,
        private readonly string $organizationId,
    ) {}

    public function organizationId(): string
    {
        return $this->organizationId;
    }

    /**
     * @return list<object>
     */
    public function middleware(): array
    {
        return [new WithOrganizationContext];
    }

    public function handle(ResolvesHostnames $resolver, SsrfGuard $guard): void
    {
        $delivery = WebhookDelivery::query()->find($this->deliveryId);

        if ($delivery === null) {
            return;
        }

        $endpoint = $delivery->endpoint;

        if ($endpoint === null || ! $endpoint->active) {
            return;
        }

        $attempt = $delivery->attempt + 1;
        $maxAttempts = config()->integer('webhooks.max_attempts');

        if ($attempt > $maxAttempts) {
            return;
        }

        $host = parse_url($endpoint->url, PHP_URL_HOST);

        if (! is_string($host)) {
            $this->block($delivery, $endpoint, $attempt);

            return;
        }

        // The send-time range check: the resolver's answer is re-checked here
        // even though the URL passed validation when it was saved.
        $addresses = $resolver->resolve($host);
        $pinned = $addresses[0] ?? null;

        foreach ($addresses as $address) {
            if (! $guard->isPublicIp($address)) {
                $pinned = null;

                break;
            }
        }

        if ($pinned === null) {
            $this->block($delivery, $endpoint, $attempt);

            return;
        }

        $body = json_encode($delivery->payload, JSON_THROW_ON_ERROR);
        $timestamp = now()->getTimestamp();
        $signature = Signature::sign($body, $endpoint->secret, $timestamp);
        $port = parse_url($endpoint->url, PHP_URL_PORT) ?? 443;

        $started = hrtime(true);

        try {
            $response = Http::withHeaders([
                'X-Signature' => $signature,
                'X-Timestamp' => (string) $timestamp,
            ])
                ->timeout(config()->integer('webhooks.timeout'))
                ->withOptions([
                    'allow_redirects' => false,
                    'curl' => [CURLOPT_RESOLVE => [$host.':'.$port.':'.$pinned]],
                ])
                ->withBody($body, 'application/json')
                ->post($endpoint->url);
        } catch (ConnectionException) {
            $response = null;
        }

        $duration = (int) ((hrtime(true) - $started) / 1_000_000);
        $sent = $response?->successful() === true;

        $delivery->update([
            'attempt' => $attempt,
            'status' => $sent ? 'sent' : 'failed',
            'status_code' => $response?->status(),
            'response_snippet' => $response === null ? null : $this->scrub($response, $endpoint->secret, $signature),
            'duration_ms' => $duration,
            'next_attempt_at' => null,
        ]);

        if ($sent) {
            $endpoint->update(['consecutive_failures' => 0]);

            return;
        }

        $this->retryOrGiveUp($delivery, $endpoint, $attempt, $maxAttempts);
    }

    /**
     * A refused send is a final failure immediately: retrying a private
     * address will not make it public, and nothing was sent to retry.
     */
    private function block(WebhookDelivery $delivery, WebhookEndpoint $endpoint, int $attempt): void
    {
        $delivery->update([
            'attempt' => $attempt,
            'status' => 'blocked',
            'next_attempt_at' => null,
        ]);

        $this->recordFinalFailure($endpoint);
    }

    private function retryOrGiveUp(WebhookDelivery $delivery, WebhookEndpoint $endpoint, int $attempt, int $maxAttempts): void
    {
        if ($attempt >= $maxAttempts) {
            $this->recordFinalFailure($endpoint);

            return;
        }

        $delay = 30 * (4 ** ($attempt - 1));

        $delivery->update(['next_attempt_at' => now()->addSeconds($delay)]);

        dispatch(new self($this->deliveryId, $this->organizationId))->delay($delay);
    }

    private function recordFinalFailure(WebhookEndpoint $endpoint): void
    {
        $failures = $endpoint->consecutive_failures + 1;
        $deactivate = $failures >= config()->integer('webhooks.deactivate_after');

        $endpoint->update([
            'consecutive_failures' => $failures,
            ...$deactivate ? ['active' => false] : [],
        ]);

        if (! $deactivate) {
            return;
        }

        $owners = Role::query()
            ->where('organization_id', $endpoint->organization_id)
            ->where('protected', true)
            ->first()
            ?->users;

        if ($owners !== null && $owners->isNotEmpty()) {
            Notification::send($owners, new WebhookEndpointDeactivated);
        }
    }

    private function scrub(Response $response, string $secret, string $signature): string
    {
        return mb_substr(
            str_replace([$secret, $signature], '[redacted]', $response->body()),
            0,
            500,
        );
    }
}
