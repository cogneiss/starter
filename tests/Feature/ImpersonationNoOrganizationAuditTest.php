<?php

declare(strict_types=1);

use App\Models\Activity;
use App\Models\ImpersonationLog;
use App\Models\Organization;
use App\Models\User;

beforeEach(function (): void {
    config()->set('features.defaults.impersonation-enabled', true);
    $this->admin = User::factory()->superAdmin()->forOrganization(Organization::factory()->create())->create();
});

it('writes a platform-level activity row for a target with no organization', function (): void {
    $target = User::factory()->create(['current_organization_id' => null]);

    $this->actingAs($this->admin)->post(route('user-impersonation.store', $target));

    $log = ImpersonationLog::query()->sole();
    $entry = Activity::withoutOrganizationScope()->where('subject_id', $log->id)->sole();

    expect($log->organization_id)->toBeNull()
        ->and($entry->organization_id)->toBeNull()
        ->and($entry->event)->toBe('created');
});

it('files the activity row under the target’s organization, not the admin’s', function (): void {
    $organization = Organization::factory()->create();
    $target = User::factory()->forOrganization($organization)->create();

    $this->actingAs($this->admin)->post(route('user-impersonation.store', $target));

    $log = ImpersonationLog::query()->sole();
    $entry = Activity::withoutOrganizationScope()->where('subject_id', $log->id)->sole();

    expect($entry->organization_id)->toBe($organization->id);
});
