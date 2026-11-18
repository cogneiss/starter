<?php

declare(strict_types=1);

use App\Models\Activity;
use App\Models\Organization;
use App\Models\OrganizationInvitation;
use App\Models\User;
use App\Support\ApiAbilities;
use App\Support\ResourceQuery;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Testing\TestResponse;

/**
 * Run one request and return the SQL it sent.
 *
 * @param  Closure(): TestResponse  $request
 * @return array{TestResponse, list<string>}
 */
function queriesDuring(Closure $request): array
{
    DB::flushQueryLog();
    DB::enableQueryLog();

    try {
        $response = $request();
    } finally {
        DB::disableQueryLog();
    }

    return [$response, array_column(DB::getQueryLog(), 'query')];
}

/**
 * Seed one row and measure the list, then seed a full page and measure again.
 * Seeding can flush the permission cache (a new member is assigned a role),
 * so each measurement follows a warm-up request that refills it.
 *
 * @param  Closure(int): mixed  $seed
 * @param  Closure(): TestResponse  $visit
 * @return array{array{TestResponse, list<string>}, array{TestResponse, list<string>}}
 */
function shortThenFullPage(Closure $seed, Closure $visit): array
{
    $measure = function (int $count) use ($seed, $visit): array {
        $seed($count);
        $visit();

        return queriesDuring($visit);
    };

    return [$measure(1), $measure(ResourceQuery::PER_OPTIONS[0])];
}

/**
 * @param  list<string>  $queries
 */
function tallied(array $queries): string
{
    $counts = array_count_values($queries);

    return "A full page sent more queries than a short one:\n".implode("\n", array_map(
        fn (string $sql, int $times): string => "{$times} x {$sql}",
        array_keys($counts),
        $counts,
    ));
}

/**
 * A list screen costs a fixed number of queries, whatever the page holds.
 * Automatic eager loading batches plain relation reads, so what this catches
 * is a per-row query that bypasses it: an explicit loadMissing() on one model
 * (Spatie's getRoleNames() is one), a count(), an exists().
 */
it('costs the same queries for a full page as for one row', function (string $route, string $rows, Closure $seed): void {
    $organization = Organization::factory()->create();
    $owner = User::factory()->forOrganization($organization)->create();

    // Onboarding side effects are audited; start each list from its own rows.
    Activity::withoutOrganizationScope()->where('organization_id', $organization->id)->delete();

    $this->actingAs($owner);

    [[$short, $few], [$full, $many]] = shortThenFullPage(
        fn (int $count): mixed => $seed($organization, $owner, $count),
        fn (): TestResponse => $this->get(route($route))->assertOk(),
    );

    expect(count($short->inertiaProps($rows)))->toBeLessThan(ResourceQuery::PER_OPTIONS[0])
        ->and($full->inertiaProps($rows))->toHaveCount(ResourceQuery::PER_OPTIONS[0])
        ->and(count($many))->toBe(count($few), tallied($many));
})->with([
    'members' => [
        'organization-member.edit',
        'members.rows',
        fn (Organization $organization, User $owner, int $count) => User::factory()->forOrganization($organization)->count($count)->create(),
    ],
    'invitations' => [
        'organization-invitation.index',
        'invitations.rows',
        fn (Organization $organization, User $owner, int $count) => OrganizationInvitation::factory()->count($count)->create(['organization_id' => $organization->id]),
    ],
    'audit log' => [
        'audit-log.index',
        'entries.rows',
        fn (Organization $organization, User $owner, int $count) => Activity::factory()->count($count)->for($owner, 'causer')->create(['organization_id' => $organization->id]),
    ],
]);

it('costs the read API the same queries for a full page of members as for one', function (): void {
    $organization = Organization::factory()->create();
    $owner = User::factory()->forOrganization($organization)->create();
    [, $bearer] = apiBearer($organization, abilities: ApiAbilities::all(), user: $owner);

    [[$short, $few], [$full, $many]] = shortThenFullPage(
        function (int $count) use ($organization): mixed {
            // The API request left sanctum as the default guard, and the
            // factory assigns each new member a web-guard role.
            Auth::shouldUse('web');

            return User::factory()->forOrganization($organization)->count($count)->create();
        },
        fn (): TestResponse => $this->withHeader('Authorization', $bearer)->getJson(route('api.resources.index', 'organization-members'))->assertOk(),
    );

    expect(count($short->json('rows')))->toBeLessThan(ResourceQuery::PER_OPTIONS[0])
        ->and($full->json('rows'))->toHaveCount(ResourceQuery::PER_OPTIONS[0])
        ->and(count($many))->toBe(count($few), tallied($many));
});
