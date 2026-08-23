<?php

declare(strict_types=1);

use App\Concerns\BelongsToOrganization;
use App\Exceptions\OrganizationContextMissing;
use App\Models\Organization;
use App\Support\OrganizationContext;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Attributes\WithoutTimestamps;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A stand-in for any organization-scoped model, so the trait is exercised
 * without depending on a model a later phase happens to add.
 */
#[Table(name: 'scoped_notes')]
#[WithoutTimestamps]
final class ScopedNote extends Model
{
    use BelongsToOrganization;
    use HasFactory;

    protected $guarded = [];
}

beforeEach(function (): void {
    Schema::create('scoped_notes', function (Blueprint $table): void {
        $table->id();
        $table->uuid('organization_id');
        $table->string('title');
    });
});

it('only reads rows of the bound organization', function (): void {
    $mine = Organization::factory()->create();
    $theirs = Organization::factory()->create();

    ScopedNote::query()->withoutGlobalScope('organization')->insert([
        ['organization_id' => $mine->id, 'title' => 'mine'],
        ['organization_id' => $theirs->id, 'title' => 'theirs'],
    ]);

    $titles = resolve(OrganizationContext::class)
        ->runAs($mine, fn (): array => ScopedNote::query()->pluck('title')->all());

    expect($titles)->toBe(['mine']);
});

it('fills the organization on create', function (): void {
    $organization = Organization::factory()->create();

    $note = resolve(OrganizationContext::class)->runAs(
        $organization,
        fn (): ScopedNote => ScopedNote::query()->create(['title' => 'filled']),
    );

    expect($note->organization_id)->toBe($organization->id)
        ->and($note->organization()->first()->is($organization))->toBeTrue();
});

it('throws when strict and no organization is bound', function (): void {
    expect(fn () => ScopedNote::query()->get())
        ->toThrow(OrganizationContextMissing::class);
});

it('returns nothing when not strict and no organization is bound', function (): void {
    config()->set('organizations.strict', false);

    $organization = Organization::factory()->create();

    ScopedNote::query()->withoutGlobalScope('organization')->insert([
        ['organization_id' => $organization->id, 'title' => 'hidden'],
    ]);

    expect(ScopedNote::query()->get())->toBeEmpty();
});

it('reads across organizations when the scope is bypassed', function (): void {
    $first = Organization::factory()->create();
    $second = Organization::factory()->create();

    ScopedNote::query()->withoutGlobalScope('organization')->insert([
        ['organization_id' => $first->id, 'title' => 'one'],
        ['organization_id' => $second->id, 'title' => 'two'],
    ]);

    expect(ScopedNote::withoutOrganizationScope()->pluck('title')->all())
        ->toBe(['one', 'two']);
});
