<?php

declare(strict_types=1);

use App\Models\Organization;
use App\Resources\ResourceContract;
use App\Resources\ResourceRegistry;
use App\Support\ResourceFilter;

/**
 * The catalogue is the registry, serialized: names, required abilities, filters
 * and sorts all diffed against the registry itself, so registering a resource
 * is the only step to publishing it.
 */
beforeEach(function (): void {
    $this->organization = Organization::factory()->create();

    [$this->token, $this->bearer] = apiBearer($this->organization);
});

function expectedCatalogue(): array
{
    return array_map(fn (ResourceContract $resource): array => [
        'key' => $resource->key(),
        'label' => $resource->label(),
        'url' => url('/api/v1/'.$resource->key()),
        'ability' => 'read:'.$resource->key(),
        'filters' => array_map(fn (ResourceFilter $filter): string => $filter->key, $resource->filters()),
        'sorts' => $resource->sortable(),
    ], array_values(resolve(ResourceRegistry::class)->all()));
}

it('catalogue matches the registry exactly', function (): void {
    $response = $this->withHeader('Authorization', $this->bearer)
        ->getJson('/api/v1');

    $response->assertOk();

    expect($response->json('resources'))->toBe(expectedCatalogue());
});

it('catalogue picks up a newly registered resource', function (): void {
    $key = withFakeResource();

    $response = $this->withHeader('Authorization', $this->bearer)
        ->getJson('/api/v1');

    $response->assertOk();

    $keys = array_column($response->json('resources'), 'key');

    expect($keys)->toContain($key)
        ->and($response->json('resources'))->toBe(expectedCatalogue());
});
