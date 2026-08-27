<?php

declare(strict_types=1);

use App\Models\Organization;
use App\Support\OrganizationContext;
use Illuminate\Support\Facades\Route;
use Tests\Fixtures\Resources\Filters\AuditLogFixture;
use Tests\Fixtures\Resources\Filters\FilteredList;

beforeEach(function (): void {
    $organization = Organization::factory()->create();

    resolve(OrganizationContext::class)->set($organization);

    AuditLogFixture::seed($organization);

    // A real request through the real kit: the query string is only hostile if
    // something actually parses it.
    Route::get('/malformed-filters', fn () => response()->json(
        FilteredList::for(request()->query())->toArray(),
    ));
});

it('discards a filter the URL made up and answers with a list', function (array $filters): void {
    $response = $this->get('/malformed-filters?'.http_build_query(['f' => $filters]));

    $response->assertOk();

    expect($response->getStatusCode())->toBe(200)->not->toBe(500)
        // Nothing was filtered, because nothing in the query string meant
        // anything a declared filter could apply.
        ->and($response->json('total'))->toBe(2);
})->with([
    'unknown facet key' => [['nope' => 'ok']],
    'array where a scalar belongs' => [['status' => ['ok']]],
    'non-numeric range bound' => [['tokens' => ['min' => 'many']]],
    'reversed date range' => [['used' => ['from' => '2026-03-01', 'to' => '2026-01-01']]],
    'reversed numeric range' => [['tokens' => ['min' => '900', 'max' => '100']]],
    'date bound that is not a date' => [['used' => ['from' => 'yesterday']]],
    'date bound that no calendar has' => [['used' => ['to' => '2026-02-31']]],
    'scalar where a list belongs' => [['tier' => 'cheap']],
]);

it('ignores only the malformed half of a query string', function (): void {
    $response = $this->get('/malformed-filters?'.http_build_query([
        'f' => ['status' => 'ok', 'tokens' => ['min' => 'many']],
    ]));

    $response->assertOk();

    expect($response->json('total'))->toBe(1)
        ->and($response->json('query.filters'))->toBe(['status' => 'ok']);
});
