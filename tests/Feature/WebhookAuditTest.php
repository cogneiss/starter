<?php

declare(strict_types=1);

use App\Models\Activity;
use App\Models\Organization;
use App\Models\User;
use App\Models\WebhookEndpoint;

/**
 * Endpoint administration is audited: create, update and delete each leave
 * exactly one line in the ledger.
 */
beforeEach(function (): void {
    $this->organization = Organization::factory()->create();
    $this->owner = User::factory()->forOrganization($this->organization)->create();

    fakeWebhookDns();
});

function webhookAuditEntries(object $subject, string $event): int
{
    return Activity::withoutOrganizationScope()
        ->where('subject_type', $subject->getMorphClass())
        ->where('subject_id', $subject->getKey())
        ->where('event', $event)
        ->count();
}

it('writes exactly one entry for an endpoint creation', function (): void {
    $this->actingAs($this->owner)->post(route('webhook.store'), [
        'url' => 'https://hooks.example.com/in',
        'events' => ['users.created'],
    ]);

    $endpoint = WebhookEndpoint::withoutOrganizationScope()->sole();

    expect(webhookAuditEntries($endpoint, 'created'))->toBe(1);
});

it('writes exactly one entry for an endpoint update', function (): void {
    $endpoint = WebhookEndpoint::factory()->create(['organization_id' => $this->organization->id]);

    $this->actingAs($this->owner)->patch(route('webhook.update', $endpoint), ['active' => false]);

    expect(webhookAuditEntries($endpoint, 'updated'))->toBe(1);
});

it('writes exactly one entry for an endpoint deletion', function (): void {
    $endpoint = WebhookEndpoint::factory()->create(['organization_id' => $this->organization->id]);

    $this->actingAs($this->owner)->delete(route('webhook.destroy', $endpoint));

    expect(webhookAuditEntries($endpoint, 'deleted'))->toBe(1);
});
