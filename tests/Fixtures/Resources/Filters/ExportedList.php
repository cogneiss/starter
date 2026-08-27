<?php

declare(strict_types=1);

namespace Tests\Fixtures\Resources\Filters;

use App\Http\Controllers\Concerns\ListsResources;
use App\Resources\ResourceRegistry;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * The export half of the list kit, driven over the fixture resource.
 *
 * Like {@see FilteredList}, this goes through the trait rather than around it:
 * the same resource, the same scoped query and the same request parsing a screen
 * would use, so what a test proves about the export is true of the real one.
 */
final class ExportedList
{
    use ListsResources;

    /**
     * @param  array<string, mixed>  $parameters
     */
    public static function for(array $parameters): StreamedResponse
    {
        app()->instance(ResourceRegistry::class, new ResourceRegistry(
            directory: __DIR__,
            namespace: __NAMESPACE__.'\\',
        ));

        $request = Request::create(
            '/audit-logs?'.http_build_query($parameters),
            server: ['HTTP_ACCEPT' => 'text/csv'],
        );

        $request->setUserResolver(fn () => auth()->user());

        abort_unless(new self()->exportsCsv($request), 406);

        return new self()->exportResource('audit-logs', $request);
    }
}
