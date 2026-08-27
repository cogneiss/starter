<?php

declare(strict_types=1);

namespace App\Actions;

use App\Resources\ResourceColumn;
use App\Resources\ResourceContract;
use App\Support\ResourceQuery;
use Illuminate\Contracts\Auth\Authenticatable;
use RuntimeException;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * A list, as a spreadsheet.
 *
 * The export runs the same query the screen ran: the resource's own scoped
 * query, narrowed by the same term and filters. That matters more here than
 * anywhere else in the list kit — a screen that leaks one row shows one row,
 * while an export that loses its organization scope hands over the whole table
 * in a single file. So the scope is part of the builder, before a row is read,
 * and there is no filtering step afterwards for anyone to weaken.
 *
 * Rows are streamed rather than collected: an export is the one place a list has
 * no page size, and a tenant with a long history should not decide how much
 * memory the web server needs.
 */
final readonly class ExportResource
{
    /**
     * Rows per round trip while streaming. Big enough that a long export is not
     * dominated by query overhead, small enough to stay off the heap.
     */
    private const int CHUNK = 500;

    public function handle(ResourceContract $resource, ResourceQuery $query, ?Authenticatable $user): StreamedResponse
    {
        $columns = ResourceColumn::visibleTo($resource->columns(), $user);

        $rows = $query->applyTo($resource->scopedQuery(), $resource)
            ->with(ResourceQuery::relationsIn(array_column($columns, 'key')));

        return response()->streamDownload(function () use ($columns, $rows): void {
            $handle = fopen('php://output', 'w') ?: throw new RuntimeException('Cannot open the response stream.');

            fputcsv($handle, array_column($columns, 'label'), escape: '\\');

            foreach ($rows->lazyById(self::CHUNK) as $record) {
                $fields = [];

                foreach ($columns as $column) {
                    $fields[] = $column->valueFor($record);
                }

                fputcsv($handle, $fields, escape: '\\');
            }

            fclose($handle);
        }, $resource->key().'.csv', ['Content-Type' => 'text/csv']);
    }
}
