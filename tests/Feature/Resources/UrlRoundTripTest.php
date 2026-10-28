<?php

declare(strict_types=1);

use App\Support\ResourceQuery;
use Illuminate\Http\Request;
use Tests\Fixtures\Resources\Filters\AuditLogResource;

it('reproduces the identical query after a trip through the address bar', function (): void {
    $resource = new AuditLogResource();

    $parameters = [
        'q' => 'summarise',
        'sort' => 'total_tokens',
        'dir' => 'desc',
        'page' => '3',
        'per' => '50',
        'f' => [
            'status' => 'ok',
            'tier' => ['cheap', 'smart'],
            'active' => '1',
            'tokens' => ['min' => '50', 'max' => '200'],
            'used' => ['from' => '2026-01-01', 'to' => '2026-01-31'],
        ],
    ];

    $original = ResourceQuery::fromRequest(request: requestFor($parameters), resource: $resource);

    // The URL a person would copy: everything the server did not assume.
    $url = $original->toQueryParameters($resource);

    $reopened = ResourceQuery::fromRequest(requestFor($url), $resource);

    expect($reopened->toArray())->toBe($original->toArray())
        ->and($original->filters)->toHaveCount(5);
});

it('carries every filter type through the URL, not just the scalar ones', function (string $key): void {
    $resource = new AuditLogResource();

    $parameters = ['f' => [
        'status' => 'ok',
        'tier' => ['cheap', 'smart'],
        'active' => '0',
        'tokens' => ['min' => '50'],
        'used' => ['to' => '2026-01-31'],
    ]];

    $original = ResourceQuery::fromRequest(requestFor($parameters), $resource);
    $reopened = ResourceQuery::fromRequest(requestFor($original->toQueryParameters($resource)), $resource);

    expect($reopened->filters[$key] ?? null)->toBe($original->filters[$key] ?? null)
        ->and($reopened->filters[$key] ?? null)->not->toBeNull();
})->with(['status', 'tier', 'active', 'tokens', 'used']);

it('settles on one canonical URL however the parameters arrive', function (array $parameters): void {
    $resource = new AuditLogResource();

    $canonical = ResourceQuery::fromRequest(requestFor($parameters), $resource)->toQueryParameters($resource);
    $again = ResourceQuery::fromRequest(requestFor($canonical), $resource)->toQueryParameters($resource);

    expect($again)->toBe($canonical);
})->with([
    'nothing' => [[]],
    'defaults spelled out' => [['q' => '', 'page' => '1', 'dir' => 'asc', 'f' => []]],
    'unknown sort column' => [['sort' => 'password', 'dir' => 'desc']],
    'direction without a sort' => [['dir' => 'desc']],
    'nonsense paging' => [['page' => '-4', 'per' => '7']],
    'unknown filter key' => [['f' => ['nope' => 'x', 'status' => 'ok']]],
    'malformed filter values' => [['f' => ['tokens' => 'abc', 'used' => ['from' => 'not-a-date'], 'tier' => 'cheap']]],
    'padded term' => [['q' => '  summarise  ']],
]);

/**
 * @param  array<string, mixed>  $parameters
 */
function requestFor(array $parameters): Request
{
    return Request::create('/audit-logs?'.http_build_query($parameters));
}
