<?php

declare(strict_types=1);

use App\Concerns\BelongsToOrganization;
use App\Listeners\RecordModelActivity;
use App\Models\Activity;
use App\Models\ApiToken;
use App\Models\Organization;
use App\Models\SavedSearch;
use App\Models\User;
use App\Resources\Definitions\AuditLogResource;
use App\Support\OrganizationContext;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

beforeEach(function (): void {
    $this->organization = Organization::factory()->create();
    $this->member = User::factory()->forOrganization($this->organization)->create();
});

it('stamps every entry with the organization bound at write time, on the raw row', function (): void {
    Auth::login($this->member);

    $token = resolve(OrganizationContext::class)->runAs(
        $this->organization,
        fn (): ApiToken => ApiToken::factory()->create(['organization_id' => $this->organization->id]),
    );

    // The raw table row, read without the model and without its scope: the
    // stamp must be in the bytes the database holds, not an accessor.
    $row = DB::table('activity_log')
        ->where('subject_type', $token->getMorphClass())
        ->where('subject_id', $token->id)
        ->where('event', 'created')
        ->sole();

    expect($row->organization_id)->toBe($this->organization->id)
        ->and($row->causer_id)->toBe($this->member->id);
});

it('cannot read another organization’s entries at the query level', function (): void {
    $theirs = Organization::factory()->create();

    Activity::factory()->create(['organization_id' => $this->organization->id, 'description' => 'ours']);
    Activity::factory()->create(['organization_id' => $theirs->id, 'description' => 'theirs']);

    $descriptions = resolve(OrganizationContext::class)->runAs(
        $this->organization,
        fn () => Activity::query()->pluck('description'),
    );

    // Factory setup writes entries of its own (memberships, onboarding), so
    // the assertion is presence and absence, not an exact list.
    expect($descriptions->all())->toContain('ours')
        ->and($descriptions->all())->not->toContain('theirs');

    // Filtering by the foreign row's own values cannot widen the scope: the
    // organization is a where clause on the query, not a post-fetch check.
    $named = resolve(OrganizationContext::class)->runAs(
        $this->organization,
        fn () => Activity::query()->where('description', 'theirs')->count(),
    );

    expect($named)->toBe(0);
});

it('audits exactly the BelongsToOrganization models, discovered by reflection', function (): void {
    $reflected = collect(classesIn(app_path('Models'), 'App\Models'))
        ->filter(fn (string $class): bool => in_array(BelongsToOrganization::class, class_uses_recursive($class), true))
        // The one reasoned exclusion: the activity model itself. Auditing the
        // audit table would write an entry about writing an entry, forever.
        ->reject(fn (string $class): bool => $class === Activity::class)
        ->values()
        ->all();

    $declared = collect(config()->array('audit.models'))->sort()->values()->all();

    // Strict equality in both directions: a scoped model missing from the
    // declared list is unaudited, a declared model without the concern is a
    // stale entry. Either way this fails.
    expect($declared)->toBe($reflected);
});

it('writes create, update and delete entries for every audited model', function (): void {
    Storage::fake('local');
    Auth::login($this->member);

    /** @var list<class-string<Model>> $audited */
    $audited = [...config()->array('audit.models'), ...config()->array('audit.extra')];

    resolve(OrganizationContext::class)->runAs($this->organization, function () use ($audited): void {
        foreach ($audited as $class) {
            $model = $class::factory()->create(['organization_id' => $this->organization->id]);

            $entriesFor = fn (string $event): int => Activity::query()
                ->where('subject_type', $model->getMorphClass())
                ->where('subject_id', $model->getKey())
                ->where('event', $event)
                ->count();

            expect($entriesFor('created'))->toBe(1, $class.' wrote no created entry');

            $created = Activity::query()
                ->where('subject_type', $model->getMorphClass())
                ->where('subject_id', $model->getKey())
                ->where('event', 'created')
                ->sole();

            expect($created->organization_id)->toBe($this->organization->id)
                ->and($created->causer_id)->toBe($this->member->id);

            // Append-only models refuse update and delete outright — a
            // mutation the model itself forbids has nothing to audit. The
            // refusal is discovered by attempting it, never by a second
            // hand-maintained list.
            $this->travel(1)->minute();

            try {
                // A model without an updated_at column cannot be touched: the
                // save writes nothing and fires no event, so no entry is owed.
                // getChanges() is the ground truth for whether a row changed.
                $model->touch();
                $changed = $model->getChanges() !== [];
                expect($entriesFor('updated'))->toBe($changed ? 1 : 0, $class.' wrote no updated entry');
            } catch (LogicException) {
                expect($entriesFor('updated'))->toBe(0);
            }

            try {
                $model->delete();
                expect($entriesFor('deleted'))->toBe(1, $class.' wrote no deleted entry');
            } catch (LogicException) {
                expect($entriesFor('deleted'))->toBe(0);
            }
        }
    });
});

it('writes nothing when no organization can be resolved for the entry', function (): void {
    $before = Activity::withoutOrganizationScope()->count();

    // Unsaved model, no organization attribute, no bound context: the listener
    // has no organization to stamp and must refuse to write, not guess.
    resolve(RecordModelActivity::class)->handle(
        'eloquent.created: '.SavedSearch::class,
        [new SavedSearch()],
    );

    expect(Activity::withoutOrganizationScope()->count())->toBe($before);
});

it('allows viewing an entry only inside its own organization', function (): void {
    $own = Activity::factory()->create(['organization_id' => $this->organization->id]);
    $foreign = Activity::factory()->create(['organization_id' => Organization::factory()->create()->id]);

    Auth::login($this->member);

    resolve(OrganizationContext::class)->runAs($this->organization, function () use ($own, $foreign): void {
        expect($this->member->can('view', $own))->toBeTrue()
            ->and($this->member->can('view', $foreign))->toBeFalse();
    });
});

it('describes an entry by its timestamp and offers no causer options without a bound organization', function (): void {
    $entry = Activity::factory()->create(['organization_id' => $this->organization->id]);

    $resource = new AuditLogResource();

    expect($resource->recordDescription($entry))->toBe((string) $entry->created_at);

    $causerFilter = collect($resource->filters())->firstWhere('key', 'causer');

    expect($causerFilter->options)->toBe([]);
});
