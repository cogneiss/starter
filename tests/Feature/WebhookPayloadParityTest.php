<?php

declare(strict_types=1);

use App\Models\Organization;
use App\Models\User;
use App\Models\WebhookDelivery;
use App\Models\WebhookEndpoint;
use App\Support\ApiAbilities;
use App\Support\OrganizationContext;
use Illuminate\Support\Facades\Http;

/**
 * A webhook payload is the resource's registered data class — byte-for-byte
 * the serialization /api/v1 returns for the same record, in one test.
 */
beforeEach(function (): void {
    $this->organization = Organization::factory()->create();
    $this->owner = User::factory()->forOrganization($this->organization)->create();

    fakeWebhookDns();
});

it('sends exactly the /api/v1 serialization as the payload', function (): void {
    WebhookEndpoint::factory()->create([
        'organization_id' => $this->organization->id,
        'url' => 'https://hooks.example.com/in',
        'events' => ['users.updated'],
    ]);

    $member = User::factory()->forOrganization($this->organization, 'Member')->create();

    Http::fake(fn () => Http::response('ok'));

    resolve(OrganizationContext::class)->runAs(
        $this->organization,
        fn () => $member->update(['name' => 'Renamed Member']),
    );

    $delivery = WebhookDelivery::withoutOrganizationScope()->sole();

    expect($delivery->event)->toBe('users.updated')
        ->and($delivery->status)->toBe('sent');

    [, $bearer] = apiBearer($this->organization, abilities: ApiAbilities::all(), user: $this->owner);

    $api = $this->withHeader('Authorization', $bearer)
        ->getJson('/api/v1/users/'.$member->id)
        ->assertOk()
        ->json();

    expect($delivery->payload['data'])->toEqual($api)
        ->and($delivery->payload['event'])->toBe('users.updated');
});
