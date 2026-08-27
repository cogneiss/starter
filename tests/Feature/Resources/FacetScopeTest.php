<?php

declare(strict_types=1);

use App\Models\Organization;
use App\Support\OrganizationContext;
use Tests\Fixtures\Resources\Filters\AuditLogFixture;
use Tests\Fixtures\Resources\Filters\FilteredList;

it('keeps a foreign record out of the list whichever filter type is applied', function (string $type): void {
    $mine = Organization::factory()->create();
    $theirs = Organization::factory()->create();

    // The foreign record matches the filter exactly. If the organization were
    // anything but a where clause on the query, this is the row that leaks.
    $foreign = AuditLogFixture::matching($theirs);

    resolve(OrganizationContext::class)->runAs($mine, function () use ($mine, $type, $foreign): void {
        $ours = AuditLogFixture::matching($mine);

        $list = FilteredList::for(['f' => AuditLogFixture::narrowing()[$type]]);

        expect($list->total)->toBe(1)
            ->and(array_column($list->rows, 'id'))
            ->toBe([$ours->id])
            ->not->toContain($foreign->id);
    });
})->with(['select', 'multi-select', 'boolean', 'range', 'date-range']);

it('leaves a foreign record out of the facet counts as well as the rows', function (): void {
    $mine = Organization::factory()->create();
    $theirs = Organization::factory()->create();

    AuditLogFixture::matching($theirs);
    AuditLogFixture::other($theirs);

    resolve(OrganizationContext::class)->runAs($mine, function () use ($mine): void {
        AuditLogFixture::matching($mine);

        $list = FilteredList::for([]);

        $status = null;

        foreach ($list->filters as $filter) {
            if ($filter->key === 'status') {
                $status = array_column($filter->options, 'count', 'value');
            }
        }

        // A count is a query too: reporting "blocked: 1" would disclose that
        // another organization has a blocked record.
        expect($status)->toBe(['ok' => 1, 'blocked' => 0]);
    });
});
