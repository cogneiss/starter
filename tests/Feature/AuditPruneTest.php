<?php

declare(strict_types=1);

use App\Models\Activity;
use App\Models\Organization;
use Illuminate\Console\Scheduling\Schedule;

it('prunes entries older than the retention window and keeps younger ones', function (): void {
    $organization = Organization::factory()->create();

    $old = Activity::factory()->create([
        'organization_id' => $organization->id,
        'created_at' => now()->subDays(config()->integer('audit.retention') + 1),
    ]);
    $young = Activity::factory()->create([
        'organization_id' => $organization->id,
        'created_at' => now()->subDays(config()->integer('audit.retention') - 1),
    ]);

    $this->artisan('audit:prune')
        ->expectsOutputToContain('Pruned 1 audit log row(s).')
        ->assertSuccessful();

    $remaining = Activity::withoutOrganizationScope()->pluck('id');

    expect($remaining)->toContain($young->id)
        ->not->toContain($old->id);
});

it('runs daily on the scheduler', function (): void {
    $commands = collect(resolve(Schedule::class)->events())
        ->map(fn ($event): string => (string) $event->command);

    expect($commands->contains(fn (string $command): bool => str_contains($command, 'audit:prune')))->toBeTrue();
});
