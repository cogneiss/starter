<?php

declare(strict_types=1);

use App\Jobs\SendWebhookDelivery;
use App\Models\Organization;
use App\Models\WebhookDelivery;
use App\Models\WebhookEndpoint;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;

/**
 * A canary signing secret flows through a real delivery, and its computed
 * signature is written to a scratch file so `.work/checks-c.sh` can grep the
 * whole run's output and logs for either value afterwards. Neither may ever
 * be logged or stored in plaintext.
 */
it('never logs or stores the canary secret or its signature in plaintext', function (): void {
    $secret = 'whsec_canary_2f9c1e';

    $organization = Organization::factory()->create();
    $endpoint = WebhookEndpoint::factory()->create([
        'organization_id' => $organization->id,
        'url' => 'https://hooks.example.com/in',
        'secret' => $secret,
    ]);
    $delivery = WebhookDelivery::factory()->create([
        'organization_id' => $organization->id,
        'webhook_endpoint_id' => $endpoint->id,
    ]);

    fakeWebhookDns();

    $signature = null;

    Http::fake(function ($request) use (&$signature) {
        $signature = $request->header('X-Signature')[0];

        return Http::response('ok');
    });

    SendWebhookDelivery::dispatch($delivery->id, $organization->id);

    expect($signature)->toBeString();

    File::ensureDirectoryExists(base_path('.work/tmp'));
    File::put(base_path('.work/tmp/canary-signature.txt'), $signature.PHP_EOL);

    // The stored endpoint row never carries the plaintext secret.
    expect(json_encode($endpoint->refresh()->getAttributes()))->not->toContain($secret);

    // The delivery row carries neither the secret nor the signature.
    expect(json_encode($delivery->refresh()->getAttributes()))->not->toContain($secret)
        ->and(json_encode($delivery->getAttributes()))->not->toContain($signature);

    $log = storage_path('logs/laravel.log');

    if (is_file($log)) {
        $contents = (string) file_get_contents($log);

        expect(str_contains($contents, $secret))->toBeFalse()
            ->and(str_contains($contents, $signature))->toBeFalse();
    }
});
