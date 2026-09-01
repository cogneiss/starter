<?php

declare(strict_types=1);

use App\Admin\AdminResources;
use App\Models\Activity;
use App\Models\Organization;
use App\Models\User;

it('audits every admin page view and CSV export, naming the admin and the tenant or platform', function (): void {
    $organization = Organization::factory()->create();
    $admin = User::factory()->superAdmin()->create();

    foreach (AdminResources::keys() as $key) {
        $url = route('admin.pages', ['page' => $key]);

        // An aggregate view is filed at the platform level.
        $this->actingAs($admin)->get($url)->assertOk();

        $view = Activity::withoutOrganizationScope()
            ->where('log_name', 'admin')
            ->where('event', 'viewed')
            ->where('description', sprintf('viewed admin %s for platform', $key))
            ->latest('id')
            ->first();

        expect($view)->not->toBeNull()
            ->and($view->causer_id)->toBe($admin->id)
            ->and($view->organization_id)->toBeNull();

        // Narrowed to one organization, the entry lands on that tenant's ledger.
        $this->actingAs($admin)
            ->get($url.'?'.http_build_query(['f' => ['organization' => [$organization->id]]]))
            ->assertOk();

        if ($key !== 'organizations' && $key !== 'role-templates') {
            $tenantView = Activity::withoutOrganizationScope()
                ->where('log_name', 'admin')
                ->where('event', 'viewed')
                ->where('description', sprintf('viewed admin %s for %s', $key, $organization->id))
                ->latest('id')
                ->first();

            expect($tenantView)->not->toBeNull()
                ->and($tenantView->causer_id)->toBe($admin->id)
                ->and($tenantView->organization_id)->toBe($organization->id);
        }

        // The export is auditable in its own right.
        $this->actingAs($admin)->get($url, ['Accept' => 'text/csv'])->assertOk();

        $export = Activity::withoutOrganizationScope()
            ->where('log_name', 'admin')
            ->where('event', 'exported')
            ->where('description', sprintf('exported admin %s for platform', $key))
            ->latest('id')
            ->first();

        expect($export)->not->toBeNull()
            ->and($export->causer_id)->toBe($admin->id);
    }
});

it('audits the health panel view at the platform level', function (): void {
    $admin = User::factory()->superAdmin()->create();

    $this->actingAs($admin)->get(route('admin.index'))->assertOk();

    $view = Activity::withoutOrganizationScope()
        ->where('log_name', 'admin')
        ->where('description', 'viewed admin health for platform')
        ->sole();

    expect($view->causer_id)->toBe($admin->id)
        ->and($view->organization_id)->toBeNull();
});
