<?php

declare(strict_types=1);

use App\Actions\CreateApiToken;
use App\Admin\AdminResources;
use App\Models\Activity;
use App\Models\ApiToken;
use App\Models\FeatureOverride;
use App\Models\ImpersonationLog;
use App\Models\Organization;
use App\Models\RoleTemplate;
use App\Models\User;
use App\Models\WebhookDelivery;
use App\Models\WebhookEndpoint;
use App\Support\OrganizationContext;
use App\Support\ResourceFilter;

/**
 * One record per page whose searchable value the loop below can look for, plus
 * an organization no record points at, so the organization filter has an
 * option that must leave every page empty.
 *
 * @return array{organization: Organization, empty: Organization}
 */
function seedAdminPages(): array
{
    $organization = Organization::factory()->create(['name' => 'Acme Rockets']);
    $empty = Organization::factory()->create(['name' => 'Vacant Ltd']);

    $impersonator = User::factory()->superAdmin()->create(['name' => 'Imp Ersonator']);

    User::factory()->create(['name' => 'Zed Searchling']);
    FeatureOverride::factory()->create(['organization_id' => $organization->id]);
    RoleTemplate::factory()->create(['name' => 'Night Auditor']);
    ImpersonationLog::factory()->create(['impersonator_user_id' => $impersonator->id]);
    Activity::factory()->create(['organization_id' => $organization->id, 'description' => 'created ApiToken']);
    ApiToken::factory()->create(['organization_id' => $organization->id, 'name' => 'ci token alpha']);
    WebhookEndpoint::factory()->create(['organization_id' => $organization->id, 'url' => 'https://hooks.example.test/alpha']);

    return ['organization' => $organization, 'empty' => $empty];
}

it('serves search, a filter, sort and CSV export on every declared admin page', function (): void {
    ['empty' => $empty] = seedAdminPages();

    $admin = User::factory()->superAdmin()->create();

    $terms = [
        'organizations' => 'Acme Rockets',
        'users' => 'Searchling',
        'feature-overrides' => 'impersonation',
        'role-templates' => 'Night Auditor',
        'impersonation-log' => 'Ersonator',
        'audit-log' => 'created ApiToken',
        'api-tokens' => 'ci token alpha',
        'webhook-endpoints' => 'hooks.example.test',
    ];

    $pages = AdminResources::pages();

    expect(array_keys($pages))->toBe(array_keys($terms));

    foreach ($pages as $key => $resource) {
        $url = route('admin.pages', ['page' => $key]);

        // The plain page.
        $this->actingAs($admin)
            ->get($url)
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('admin/index')
                ->where('page', $key)
                ->has('list.rows'));

        // Search narrows: the seeded term matches, garbage matches nothing.
        $this->actingAs($admin)
            ->get($url.'?q='.urlencode($terms[$key]))
            ->assertInertia(fn ($page) => $page->where('list.total', fn ($total): bool => $total >= 1));

        $this->actingAs($admin)
            ->get($url.'?q=zzz-nothing-matches-this')
            ->assertInertia(fn ($page) => $page->where('list.total', 0));

        // At least one filter narrows to zero.
        expect($resource->filters())->not->toBeEmpty();

        $filterKeys = array_map(fn (ResourceFilter $declared): string => $declared->key, $resource->filters());

        // The audit-log accrues rows for the empty organization as these very
        // admin views are audited against it, so it narrows on an event nothing
        // in this test ever produces instead.
        $filter = match (true) {
            $key === 'audit-log' => ['f' => ['event' => ['role_changed']]],
            in_array('organization', $filterKeys, true) => ['f' => ['organization' => [$empty->id]]],
            default => ['f' => ['when' => ['from' => '2030-01-01']]],
        };

        $this->actingAs($admin)
            ->get($url.'?'.http_build_query($filter))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->where('list.total', 0));

        // Sort is accepted and echoed back.
        $sort = $resource->sortable()[0];

        $this->actingAs($admin)
            ->get($url.'?sort='.$sort.'&dir=desc')
            ->assertOk()
            ->assertInertia(fn ($page) => $page->where('list.query.sort', $sort));

        // The same URL answers as a spreadsheet.
        $this->actingAs($admin)
            ->get($url, ['Accept' => 'text/csv'])
            ->assertOk()
            ->assertHeader('Content-Type', 'text/csv; charset=UTF-8');
    }
});

it('never exposes a plaintext token value on the admin api-tokens page', function (): void {
    $organization = Organization::factory()->create();
    $member = User::factory()->forOrganization($organization)->create();

    $plaintext = resolve(OrganizationContext::class)->runAs(
        $organization,
        fn () => resolve(CreateApiToken::class)->handle($member, 'ci deploy token', ['read:users']),
    )->plainTextToken;

    $admin = User::factory()->superAdmin()->create();
    $url = route('admin.pages', ['page' => 'api-tokens']);

    $page = $this->actingAs($admin)->get($url)->assertOk();

    expect($page->getContent())->toContain('ci deploy token')
        ->not->toContain($plaintext);

    $csv = $this->actingAs($admin)
        ->get($url, ['Accept' => 'text/csv'])
        ->assertOk()
        ->streamedContent();

    expect($csv)->toContain('ci deploy token')
        ->not->toContain($plaintext);
});

it('shows the latest cross-organization failures beside the webhook endpoints page', function (): void {
    ['organization' => $organization] = seedAdminPages();

    $failed = WebhookDelivery::factory()->create([
        'organization_id' => $organization->id,
        'webhook_endpoint_id' => WebhookEndpoint::withoutOrganizationScope()->sole()->id,
        'status' => 'failed',
        'attempt' => 2,
    ]);

    $admin = User::factory()->superAdmin()->create();

    $this->actingAs($admin)
        ->get(route('admin.pages', ['page' => 'webhook-endpoints']))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('recentFailures.0.id', $failed->id)
            ->where('recentFailures.0.organization_id', $organization->id)
            ->where('recentFailures.0.status', 'failed')
            ->where('recentFailures.0.attempt', 2));

    $this->actingAs($admin)
        ->get(route('admin.pages', ['page' => 'organizations']))
        ->assertInertia(fn ($page) => $page->where('recentFailures', null));
});
