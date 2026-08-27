<?php

declare(strict_types=1);

use App\Enums\FilterType;
use App\Models\Organization;
use App\Resources\Definitions\OrganizationInvitationResource;
use App\Support\OrganizationContext;
use App\Support\ResourceFilter;
use Tests\Fixtures\Resources\Filters\AuditLogFixture;
use Tests\Fixtures\Resources\Filters\AuditLogResource;
use Tests\Fixtures\Resources\Filters\FilteredList;

it('declares every filter type on the resource, with a column of its own', function (): void {
    $filters = new AuditLogResource()->filters();

    $declared = array_map(fn (ResourceFilter $filter): FilterType => $filter->type, $filters);

    // Every case of the enum is reachable from a resource definition. A page
    // cannot add a sixth: the list only ever draws what this array says.
    expect($declared)->toEqualCanonicalizing(FilterType::cases());

    foreach ($filters as $filter) {
        expect($filter->column)->not->toBe('')
            ->and($filter->key)->not->toBe('');
    }
});

it('narrows the list with each filter type, through the query rather than the rows', function (string $type): void {
    $organization = Organization::factory()->create();

    resolve(OrganizationContext::class)->runAs($organization, function () use ($organization, $type): void {
        $records = AuditLogFixture::seed($organization);

        $list = FilteredList::for(['f' => AuditLogFixture::narrowing()[$type]]);

        // total comes from the paginator's own count query, so a filter that
        // only hid rows after fetching them would still report two.
        expect($list->total)->toBe(1)
            ->and($list->rows)->toHaveCount(1)
            ->and($list->rows[0]->id)->toBe($records['matching']->id);
    });
})->with(['select', 'multi-select', 'boolean', 'range', 'date-range']);

it('narrows on one bound of a range and leaves the other end open', function (array $filters, string $expected): void {
    $organization = Organization::factory()->create();

    resolve(OrganizationContext::class)->runAs($organization, function () use ($organization, $filters, $expected): void {
        $records = AuditLogFixture::seed($organization);

        $list = FilteredList::for(['f' => $filters]);

        expect($list->total)->toBe(1)
            ->and($list->rows[0]->id)->toBe($records[$expected]->id);
    });
})->with([
    'a floor and no ceiling' => [['tokens' => ['min' => '400']], 'other'],
    'a ceiling and no floor' => [['tokens' => ['max' => '400']], 'matching'],
    'a start and no end' => [['used' => ['from' => '2026-02-01']], 'other'],
    'an end and no start' => [['used' => ['to' => '2026-02-01']], 'matching'],
]);

it('offers no role options until an organization is bound', function (): void {
    // The invitation filters read the roles this organization has. Off a
    // request there is no organization to read them from, and asking for the
    // filters must still answer with filters rather than reaching for one.
    $roles = array_values(array_filter(
        new OrganizationInvitationResource()->filters(),
        fn (ResourceFilter $filter): bool => $filter->key === 'role',
    ));

    expect($roles)->toHaveCount(1)
        ->and($roles[0]->options)->toBe([]);
});
