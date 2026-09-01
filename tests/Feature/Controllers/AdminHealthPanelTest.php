<?php

declare(strict_types=1);

use App\Models\User;
use App\Support\Health\HealthReport;

it('renders the same checks the registered HealthReport runs, no re-implementation', function (): void {
    $admin = User::factory()->superAdmin()->create();

    $registered = array_column(resolve(HealthReport::class)->run()['checks'], 'name');

    expect($registered)->not->toBeEmpty();

    $this->actingAs($admin)
        ->get(route('admin.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('admin/health')
            ->has('report.status')
            ->where(
                'report.checks',
                fn ($checks): bool => array_column($checks->toArray(), 'name') === $registered,
            ));
});
