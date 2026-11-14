<?php

declare(strict_types=1);

namespace App\Mcp\Resources;

use App\Mcp\Concerns\ResolvesMember;
use App\Resources\ResourceContract;
use App\Resources\ResourceRegistry;
use App\Support\ResourceFilter;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\MimeType;
use Laravel\Mcp\Server\Attributes\Uri;
use Laravel\Mcp\Server\Resource;

/**
 * The registered resources a client can pass to the record tools, with the
 * fields each one can be searched, sorted and filtered by.
 *
 * It describes the registry, not anyone's rows: every value comes from code, so
 * there is no stored text here to fence. The member still has to be signed in
 * and allowed into their organization, the same gate the record tools open with.
 */
#[Uri('starter://resources/catalog')]
#[MimeType('application/json')]
#[Description('The resource keys the list-records and show-record tools accept, with their searchable, sortable and filterable fields.')]
final class ResourceCatalog extends Resource
{
    use ResolvesMember;

    public function __construct(private readonly ResourceRegistry $registry) {}

    /**
     * @throws AuthenticationException|AuthorizationException
     */
    public function handle(Request $request): Response
    {
        $this->authorizedMember($request);

        $catalog = array_map(fn (ResourceContract $resource): array => [
            'key' => $resource->key(),
            'label' => $resource->label(),
            'searchable' => $resource->searchable(),
            'sortable' => $resource->sortable(),
            'filters' => array_map(fn (ResourceFilter $filter): string => $filter->key, $resource->filters()),
        ], array_values($this->registry->all()));

        return Response::text(json_encode(['resources' => $catalog], JSON_THROW_ON_ERROR));
    }
}
