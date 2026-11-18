<?php

declare(strict_types=1);

use App\Models\Activity;
use App\Models\AiCreditLedgerEntry;
use App\Models\Organization;
use App\Models\User;
use App\Support\OrganizationContext;

beforeEach(function (): void {
    $this->organization = Organization::factory()->create();
    $this->payload = [
        'organization_id' => $this->organization->id,
        'micros' => 2_500_000,
        'reason' => 'Support goodwill',
    ];
});

it('lets a platform admin grant AI credit, writing the ledger row and the audit row', function (): void {
    $admin = User::factory()->superAdmin()->create();

    $this->actingAs($admin)
        ->get(route('admin.ai-credits.create'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('admin/ai-credit')->has('organizations')->has('maxMicros'));

    $this->post(route('admin.ai-credits.store'), $this->payload)
        ->assertRedirectToRoute('admin.pages', ['page' => 'organizations']);

    $entry = resolve(OrganizationContext::class)->runAs($this->organization, fn (): AiCreditLedgerEntry => AiCreditLedgerEntry::query()->sole());

    $audit = Activity::withoutOrganizationScope()
        ->where('log_name', 'admin')
        ->where('event', 'granted')
        ->sole();

    expect($entry->delta_micros)->toBe(2_500_000)
        ->and($entry->reason)->toBe('Support goodwill')
        ->and($audit->causer_id)->toBe($admin->id)
        ->and($audit->organization_id)->toBe($this->organization->id)
        ->and($audit->subject_id)->toBe($entry->id)
        ->and($audit->properties->all())->toBe(['micros' => 2_500_000, 'reason' => 'Support goodwill']);
});

it('offers at most twenty organizations, narrowed on the server by a name search', function (): void {
    Organization::factory()->count(25)->create();
    $wanted = Organization::factory()->create(['name' => 'Zebra Logistics']);

    $this->actingAs(User::factory()->superAdmin()->create())
        ->get(route('admin.ai-credits.create'))
        ->assertInertia(fn ($page) => $page->count('organizations', 20)->where('search', ''));

    $this->get(route('admin.ai-credits.create', ['search' => '  zebra ']))
        ->assertInertia(fn ($page) => $page
            ->where('search', 'zebra')
            ->count('organizations', 1)
            ->where('organizations.0.id', $wanted->id)
            ->where('organizations.0.name', 'Zebra Logistics')
            ->where('organizations.0.slug', $wanted->slug));
});

it('is a 404 for anyone who is not a platform admin', function (): void {
    $member = User::factory()->forOrganization($this->organization)->create();

    $this->actingAs($member)->get(route('admin.ai-credits.create'))->assertNotFound();
    $this->post(route('admin.ai-credits.store'), $this->payload)->assertNotFound();

    expect(Activity::withoutOrganizationScope()->where('event', 'granted')->exists())->toBeFalse()
        ->and(AiCreditLedgerEntry::withoutOrganizationScope()->exists())->toBeFalse();
});

it('rejects a missing, non-positive or oversized amount, an unknown organization and a missing reason', function (array $override, string $field): void {
    $this->actingAs(User::factory()->superAdmin()->create())
        ->post(route('admin.ai-credits.store'), [...$this->payload, ...$override])
        ->assertSessionHasErrors($field);

    expect(AiCreditLedgerEntry::withoutOrganizationScope()->exists())->toBeFalse();
})->with([
    'zero' => [['micros' => 0], 'micros'],
    'negative' => [['micros' => -5], 'micros'],
    'fractional' => [['micros' => 1.5], 'micros'],
    'too large' => [['micros' => 1_000_000_001], 'micros'],
    'no amount' => [['micros' => ''], 'micros'],
    'unknown organization' => [['organization_id' => '00000000-0000-0000-0000-000000000000'], 'organization_id'],
    'no reason' => [['reason' => ''], 'reason'],
]);

it('validates live through precognition', function (): void {
    $this->actingAs(User::factory()->superAdmin()->create())
        ->withHeaders(['Precognition' => 'true', 'Precognition-Validate-Only' => 'micros'])
        ->postJson(route('admin.ai-credits.store'), ['micros' => 0])
        ->assertStatus(422)
        ->assertJsonValidationErrors('micros');
});
