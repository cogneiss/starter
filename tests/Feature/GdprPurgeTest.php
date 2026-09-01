<?php

declare(strict_types=1);

use App\Models\Activity;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Console\Scheduling\Schedule;

it('hard deletes accounts past the retention window and spares the rest', function (): void {
    $due = User::factory()->create();
    $due->delete();

    $this->travel(2)->days();

    $recent = User::factory()->create();
    $recent->delete();

    // 31 days after the first deletion, 29 after the second.
    $this->travel(29)->days();

    $this->artisan('gdpr:purge')
        ->expectsOutputToContain('Purged 1 account(s).')
        ->assertSuccessful();

    expect(User::withTrashed()->find($due->id))->toBeNull()
        ->and(User::withTrashed()->find($recent->id))->not->toBeNull();
});

it('counts without deleting on a dry run', function (): void {
    $due = User::factory()->create();
    $due->delete();

    $this->travel(31)->days();

    $this->artisan('gdpr:purge', ['--dry-run' => true])
        ->expectsOutputToContain('1 account(s) due for purge.')
        ->assertSuccessful();

    expect(User::withTrashed()->find($due->id))->not->toBeNull();
});

it('audits the purge distinctly from the anonymisation', function (): void {
    $organization = Organization::factory()->create();
    $member = User::factory()->forOrganization($organization)->create();
    $member->delete();

    $this->travel(31)->days();

    $this->artisan('gdpr:purge')->assertSuccessful();

    $entry = Activity::withoutOrganizationScope()
        ->where('organization_id', $organization->id)
        ->where('event', 'purged')
        ->sole();

    expect($entry->event)->not->toBe('anonymised');
});

it('runs daily on the scheduler', function (): void {
    $commands = collect(resolve(Schedule::class)->events())
        ->map(fn ($event): string => (string) $event->command);

    expect($commands->contains(fn (string $command): bool => str_contains($command, 'gdpr:purge')))->toBeTrue();
});
