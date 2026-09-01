<?php

declare(strict_types=1);

use App\Jobs\SendWebhookDelivery;
use App\Models\Organization;
use App\Models\WebhookDelivery;
use App\Models\WebhookEndpoint;
use App\Webhooks\Signature;
use Illuminate\Support\Facades\Http;

/**
 * The verification snippet published in SECURITY.md is executed here against
 * a real delivery: what the documentation tells receivers to run must accept
 * what the application actually sends, refuse a tampered body, and refuse a
 * replayed timestamp.
 */
beforeEach(function (): void {
    $this->organization = Organization::factory()->create();

    fakeWebhookDns();
});

/**
 * Extracts and loads the exact ```php block from SECURITY.md, so these tests
 * exercise the published code, not a re-implementation of it.
 */
function documentedWebhookVerifier(): void
{
    if (function_exists('verifyWebhook')) {
        return;
    }

    preg_match('/```php\n(.*?)```/s', (string) file_get_contents(base_path('SECURITY.md')), $matches);

    expect($matches[1] ?? '')->toContain('function verifyWebhook');

    eval($matches[1]);
}

function captureWebhookRequest(Organization $organization): array
{
    $endpoint = WebhookEndpoint::factory()->create([
        'organization_id' => $organization->id,
        'url' => 'https://hooks.example.com/in',
        'secret' => 'whsec_documented_secret',
    ]);

    $delivery = WebhookDelivery::factory()->create([
        'organization_id' => $organization->id,
        'webhook_endpoint_id' => $endpoint->id,
    ]);

    $captured = [];

    Http::fake(function ($request) use (&$captured) {
        $captured = [
            'body' => $request->body(),
            'signature' => $request->header('X-Signature')[0],
            'timestamp' => (int) $request->header('X-Timestamp')[0],
        ];

        return Http::response('ok');
    });

    SendWebhookDelivery::dispatch($delivery->id, $organization->id);

    expect($captured)->not->toBe([]);

    return $captured;
}

it('documented snippet verifies a real delivery', function (): void {
    documentedWebhookVerifier();

    $captured = captureWebhookRequest($this->organization);

    expect(verifyWebhook(
        $captured['body'],
        $captured['signature'],
        $captured['timestamp'],
        'whsec_documented_secret',
    ))->toBeTrue();
});

it('documented snippet fails when one byte of the body changes', function (): void {
    documentedWebhookVerifier();

    $captured = captureWebhookRequest($this->organization);

    $tampered = $captured['body'];
    $tampered[0] = $tampered[0] === '{' ? '[' : '{';

    expect(verifyWebhook($tampered, $captured['signature'], $captured['timestamp'], 'whsec_documented_secret'))->toBeFalse()
        ->and(verifyWebhook($captured['body'], $captured['signature'], $captured['timestamp'], 'whsec_wrong_secret'))->toBeFalse();
});

it('documented snippet rejects a stale timestamp even with a genuine signature', function (): void {
    documentedWebhookVerifier();

    $body = '{"event":"users.created"}';
    $secret = 'whsec_documented_secret';
    $stale = time() - 301;

    expect(verifyWebhook($body, Signature::sign($body, $secret, $stale), $stale, $secret))->toBeFalse()
        ->and(verifyWebhook($body, Signature::sign($body, $secret, time()), time(), $secret))->toBeTrue();
});

it('verify accepts a fresh signature and refuses tampering and replays', function (): void {
    $body = '{"event":"users.updated"}';
    $secret = 'whsec_unit_secret';
    $now = time();

    $signature = Signature::sign($body, $secret, $now);

    expect(Signature::verify($body, $secret, $now, $signature))->toBeTrue()
        ->and(Signature::verify($body.' ', $secret, $now, $signature))->toBeFalse()
        ->and(Signature::verify($body, $secret, $now, $signature, tolerance: 5))->toBeTrue()
        ->and(Signature::verify($body, $secret, $now - 6, Signature::sign($body, $secret, $now - 6), tolerance: 5))->toBeFalse();

    $stale = $now - 301;

    expect(Signature::verify($body, $secret, $stale, Signature::sign($body, $secret, $stale)))->toBeFalse();
});
