<?php

declare(strict_types=1);

namespace Tests\Fixtures\Resources\Filters;

use App\Data\ResourceListData;
use App\Http\Controllers\Concerns\ListsResources;
use App\Models\AiAuditLog;
use App\Resources\ResourceRegistry;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;

/**
 * The list kit itself, driven over the fixture resource.
 *
 * A filter test goes through `listResource()` rather than around it, so what it
 * asserts about scoping and facets is what a real screen would get — the same
 * trait, the same scoped query, the same payload object.
 */
final class FilteredList
{
    use ListsResources;

    /**
     * @param  array<string, mixed>  $parameters
     */
    public static function for(array $parameters): ResourceListData
    {
        app()->instance(ResourceRegistry::class, new ResourceRegistry(
            directory: __DIR__,
            namespace: __NAMESPACE__.'\\',
        ));

        $request = Request::create('/audit-logs?'.http_build_query($parameters));

        return new self()->listResource('audit-logs', $request, function (Model $record): AuditLogRowData {
            assert($record instanceof AiAuditLog);

            return new AuditLogRowData($record->id, $record->total_tokens);
        });
    }
}
