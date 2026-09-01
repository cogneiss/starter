<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Data\ResourceListData;
use App\Http\Controllers\Concerns\ListsResources;
use App\Resources\ResourceContract;
use App\Resources\ResourceRegistry;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Spatie\LaravelData\Data;

/**
 * The read API: one controller for every registered resource.
 *
 * The routes are generic — the registry decides what exists. A key nobody
 * registered is a 404, a key the token lacks the `read:` ability for is a 403,
 * and every lookup runs through the same scoped query the web lists use, so a
 * foreign id is a 404 before any policy or ability is consulted.
 */
final readonly class ResourceController
{
    use ListsResources;

    public function index(Request $request, string $resource): ResourceListData
    {
        $definition = $this->definition($request, $resource);

        $request->validate([
            'page' => ['sometimes', 'integer', 'min:1'],
            'per' => ['sometimes', 'integer', 'between:1,100'],
        ]);

        return $this->listResource(
            $definition->key(),
            $request,
            fn (Model $record): Data => $definition->dataClass()::from($record),
        );
    }

    public function show(Request $request, string $resource, string $id): Data
    {
        $definition = $this->definition($request, $resource);

        return $definition->dataClass()::from($this->findListed($definition->key(), $id));
    }

    /**
     * The registered resource behind a URL segment. Unknown keys 404 before the
     * ability check, so an unauthorized probe cannot map which keys exist.
     */
    private function definition(Request $request, string $key): ResourceContract
    {
        $registry = resolve(ResourceRegistry::class);

        abort_unless(in_array($key, $registry->keys(), true), 404);

        abort_unless($request->user()?->tokenCan('read:'.$key) === true, 403);

        return $registry->get($key);
    }
}
