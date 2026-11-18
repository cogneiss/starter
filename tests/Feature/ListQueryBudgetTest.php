<?php

declare(strict_types=1);

use App\Models\Activity;
use App\Models\Organization;
use App\Models\OrganizationInvitation;
use App\Models\User;
use App\Support\ResourceQuery;
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
 * A list screen costs a fixed number of queries, whatever the page holds.
 * Automatic eager loading already batches relations, so what this catches is
 * a per-row count(), exists() or policy check in the row mapping.
 */
it('costs the same queries for a full page as for one row', function (string $route, string $rows, Closure $seed): void {
    $organization = Organization::factory()->create();
    $owner = User::factory()->forOrganization($organization)->create();
    $page = ResourceQuery::PER_OPTIONS[0];

    // Onboarding side effects are audited; start each list from its own rows.
    Activity::withoutOrganizationScope()->where('organization_id', $organization->id)->delete();

    $this->actingAs($owner);

    // Seeding can flush the permission cache (a new member is assigned a
    // role), so each measurement follows a warm-up request that refills it.
    $measure = function (int $count) use ($seed, $organization, $owner, $route): array {
        $seed($organization, $owner, $count);
        $this->get(route($route))->assertOk();

        return queriesDuring(fn (): TestResponse => $this->get(route($route))->assertOk());
    };

    [$short, $few] = $measure(1);
    [$full, $many] = $measure($page);

    expect(count($short->inertiaProps($rows)))->toBeLessThan($page)
        ->and($full->inertiaProps($rows))->toHaveCount($page)
        ->and(count($many))->toBe(count($few), "A full page sent more queries than a short one:\n".implode("\n", array_map(fn (string $sql, int $times): string => "{$times} x {$sql}", array_keys(array_count_values($many)), array_count_values($many))));
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
