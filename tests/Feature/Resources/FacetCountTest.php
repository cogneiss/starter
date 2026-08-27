<?php

declare(strict_types=1);

use App\Data\ResourceFilterData;
use App\Models\AiAuditLog;
use App\Models\Organization;
use App\Support\OrganizationContext;
use Illuminate\Support\Facades\DB;
use Tests\Fixtures\Resources\Filters\AuditLogFixture;
use Tests\Fixtures\Resources\Filters\FilteredList;

/**
 * @param  list<ResourceFilterData>  $filters
 * @return array<string, int>
 */
function facetCounts(array $filters, string $key): array
{
    foreach ($filters as $filter) {
        if ($filter->key === $key) {
            return array_column($filter->options, 'count', 'value');
        }
    }

    return [];
}

it('counts a facet without its own constraint, and every other facet with it', function (): void {
    $organization = Organization::factory()->create();

    resolve(OrganizationContext::class)->runAs($organization, function () use ($organization): void {
        AuditLogFixture::seed($organization);

        $list = FilteredList::for(['f' => ['status' => 'ok']]);

        expect($list->total)->toBe(1);

        // Its own constraint is left off: ticking 'blocked' has to say what it
        // would leave, not what the current selection already excluded.
        expect(facetCounts($list->filters, 'status'))->toBe(['ok' => 1, 'blocked' => 1]);

        // Every other facet is counted with the status filter applied, so the
        // tier of the excluded record counts zero rather than one.
        expect(facetCounts($list->filters, 'tier'))->toBe(['cheap' => 1, 'smart' => 0]);
    });
});

it('counts nothing for a row whose facet column is empty', function (): void {
    $organization = Organization::factory()->create();

    resolve(OrganizationContext::class)->runAs($organization, function () use ($organization): void {
        AuditLogFixture::seed($organization);

        // A nullable column groups its empty rows together, and that group is
        // not an option anyone can tick. It is dropped rather than counted
        // under a made-up label.
        AiAuditLog::factory()->create([
            'organization_id' => $organization->id,
            'tier' => null,
        ]);

        $list = FilteredList::for([]);

        expect($list->total)->toBe(3)
            ->and(facetCounts($list->filters, 'tier'))->toBe(['cheap' => 1, 'smart' => 1]);
    });
});

it('costs one grouped query per counting facet, however many rows there are', function (): void {
    $organization = Organization::factory()->create();

    resolve(OrganizationContext::class)->runAs($organization, function () use ($organization): void {
        AuditLogFixture::seed($organization);

        $grouped = [];

        DB::listen(function ($query) use (&$grouped): void {
            if (str_contains(mb_strtolower($query->sql), 'group by')) {
                $grouped[] = $query->sql;
            }
        });

        FilteredList::for([]);

        // Three of the five types offer options to count; a range and a date
        // range have no option list, so they ask the database for nothing.
        expect($grouped)->toHaveCount(3);
    });
});
