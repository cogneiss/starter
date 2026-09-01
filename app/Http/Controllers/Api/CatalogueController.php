<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Resources\ResourceContract;
use App\Resources\ResourceRegistry;
use App\Support\ResourceFilter;
use Illuminate\Http\JsonResponse;

/**
 * The API's front page: every registered resource with its URL, required
 * ability, and the filters and sorts its list accepts. The registry is the
 * single source — registering a resource is the only step to publishing it.
 */
final readonly class CatalogueController
{
    public function __invoke(): JsonResponse
    {
        $resources = array_map(fn (ResourceContract $resource): array => [
            'key' => $resource->key(),
            'label' => $resource->label(),
            'url' => url('/api/v1/'.$resource->key()),
            'ability' => 'read:'.$resource->key(),
            'filters' => array_map(fn (ResourceFilter $filter): string => $filter->key, $resource->filters()),
            'sorts' => $resource->sortable(),
        ], array_values(resolve(ResourceRegistry::class)->all()));

        return new JsonResponse(['resources' => $resources]);
    }
}
