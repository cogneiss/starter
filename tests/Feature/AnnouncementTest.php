<?php

declare(strict_types=1);

use App\Models\Activity;
use App\Models\Announcement;
use App\Models\Organization;
use App\Models\User;

/**
 * @return list<string>
 */
function announcementTitles(User $user): array
{
    $titles = [];

    test()->flushSession();
    test()->actingAs($user)->get(route('dashboard'))->assertInertia(function ($page) use (&$titles): void {
        $titles = collect($page->toArray()['props']['announcements'])->pluck('title')->all();
    });

    return $titles;
}

beforeEach(function (): void {
    $this->organization = Organization::factory()->create();
    $this->member = User::factory()->forOrganization($this->organization)->create();
});

it('Announcement shows only inside its scheduling window', function (): void {
    Announcement::factory()->create(['title' => 'live', 'starts_at' => now()->subHour(), 'ends_at' => now()->addHour()]);
    Announcement::factory()->create(['title' => 'open ended', 'starts_at' => now()->subHour(), 'ends_at' => null]);
    Announcement::factory()->create(['title' => 'future', 'starts_at' => now()->addHour()]);
    Announcement::factory()->create(['title' => 'expired', 'starts_at' => now()->subDay(), 'ends_at' => now()->subMinute()]);

    expect(announcementTitles($this->member))->toEqualCanonicalizing(['live', 'open ended']);
});

it('Announcement scopes global versus organization and never leaks across organizations', function (): void {
    $other = Organization::factory()->create();
    $outsider = User::factory()->forOrganization($other)->create();

    Announcement::factory()->create(['title' => 'everyone']);
    $ours = Announcement::factory()->create(['title' => 'ours', 'organization_id' => $this->organization->id]);
    Announcement::factory()->create(['title' => 'theirs', 'organization_id' => $other->id]);

    expect($ours->organization->is($this->organization))->toBeTrue()
        ->and(announcementTitles($this->member))->toEqualCanonicalizing(['everyone', 'ours'])
        ->and(announcementTitles($outsider))->toEqualCanonicalizing(['everyone', 'theirs']);
});

it('Announcement dismissal persists per user', function (): void {
    $announcement = Announcement::factory()->create(['title' => 'hello']);
    $colleague = User::factory()->forOrganization($this->organization)->create();

    $this->actingAs($this->member)
        ->post(route('announcement.dismiss', $announcement))
        ->assertRedirect();

    $this->post(route('announcement.dismiss', $announcement))->assertRedirect();

    expect($announcement->dismissedBy()->count())->toBe(1)
        ->and(announcementTitles($this->member))->toBe([])
        ->and(announcementTitles($colleague))->toBe(['hello']);
});

it('Announcement dismiss is a 404 for one the user cannot see', function (): void {
    $foreign = Announcement::factory()->create(['organization_id' => Organization::factory()->create()->id]);
    $expired = Announcement::factory()->create(['starts_at' => now()->subDay(), 'ends_at' => now()->subHour()]);

    $this->actingAs($this->member);

    $this->post(route('announcement.dismiss', $foreign))->assertNotFound();
    $this->post(route('announcement.dismiss', $expired))->assertNotFound();
    $this->post(route('announcement.dismiss', (string) fake()->uuid()))->assertNotFound();

    expect($foreign->dismissedBy()->count())->toBe(0);
});

it('Announcement admin CRUD works for a platform admin and is audited', function (): void {
    $admin = User::factory()->superAdmin()->create();

    $payload = [
        'title' => 'Maintenance',
        'body' => 'Back in an hour.',
        'level' => 'warning',
        'starts_at' => now()->addDay()->format('Y-m-d H:i:s'),
        'ends_at' => null,
        'organization_id' => $this->organization->id,
    ];

    $this->actingAs($admin);

    $this->get(route('admin.announcements.create'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('admin/announcement')->where('announcement', null)->has('levels')->has('organizations'));

    $this->post(route('admin.announcements.store'), $payload)
        ->assertRedirectToRoute('admin.pages', ['page' => 'announcements']);

    $announcement = Announcement::query()->sole();

    expect($announcement->organization_id)->toBe($this->organization->id);

    $this->get(route('admin.announcements.edit', $announcement))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->where('announcement.title', 'Maintenance'));

    $this->patch(route('admin.announcements.update', $announcement), [...$payload, 'title' => 'Renamed'])
        ->assertRedirectToRoute('admin.pages', ['page' => 'announcements']);

    expect($announcement->refresh()->title)->toBe('Renamed')
        ->and($announcement->organization_id)->toBe($this->organization->id);

    $this->delete(route('admin.announcements.destroy', $announcement))
        ->assertRedirectToRoute('admin.pages', ['page' => 'announcements']);

    expect(Announcement::query()->count())->toBe(0);

    foreach (['created', 'updated', 'deleted'] as $event) {
        expect(Activity::withoutOrganizationScope()
            ->where('subject_type', $announcement->getMorphClass())
            ->where('subject_id', $announcement->id)
            ->where('event', $event)
            ->exists())->toBeTrue($event);
    }
});

it('Announcement admin validates live and on submit', function (): void {
    $this->actingAs(User::factory()->superAdmin()->create());

    $this->post(route('admin.announcements.store'), [
        'title' => '',
        'level' => 'nope',
        'starts_at' => now()->format('Y-m-d H:i:s'),
        'ends_at' => now()->subDay()->format('Y-m-d H:i:s'),
    ])->assertSessionHasErrors(['title', 'body', 'level', 'ends_at']);

    $this->withHeaders(['Precognition' => 'true', 'Precognition-Validate-Only' => 'title'])
        ->postJson(route('admin.announcements.store'), ['title' => ''])
        ->assertStatus(422)
        ->assertJsonValidationErrors('title');
});

it('Announcement admin routes are a 404 without the platform ability', function (): void {
    $announcement = Announcement::factory()->create();

    $this->actingAs($this->member);

    $this->get(route('admin.announcements.create'))->assertNotFound();
    $this->post(route('admin.announcements.store'), [])->assertNotFound();
    $this->get(route('admin.announcements.edit', $announcement))->assertNotFound();
    $this->patch(route('admin.announcements.update', $announcement), [])->assertNotFound();
    $this->delete(route('admin.announcements.destroy', $announcement))->assertNotFound();

    expect(Announcement::query()->count())->toBe(1);
});
