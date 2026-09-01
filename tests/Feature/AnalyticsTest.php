<?php

declare(strict_types=1);

use App\Concerns\BelongsToOrganization;
use App\Contracts\AnalyticsReporter;
use App\Jobs\SendAnalyticsEvent;
use App\Models\Activity;
use App\Models\ApiRequestLog;
use App\Models\Organization;
use App\Models\SavedSearch;
use App\Models\User;
use App\Support\Analytics\Attributes\NoTrack;
use App\Support\Analytics\Attributes\Track;
use App\Support\Analytics\NullAnalyticsReporter;
use App\Support\Analytics\PostHogReporter;
use App\Support\OrganizationContext;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;

#[Track(['updated'])]
final class AnalyticsTrackedFixture extends Model
{
    use BelongsToOrganization;
    use HasUuids;

    protected $table = 'saved_searches';

    protected $guarded = [];

    protected $casts = ['query' => 'array'];
}

#[NoTrack]
final class AnalyticsSilencedFixture extends Model
{
    use BelongsToOrganization;
    use HasUuids;

    protected $table = 'saved_searches';

    protected $guarded = [];

    protected $casts = ['query' => 'array'];
}

beforeEach(function (): void {
    $this->organization = Organization::factory()->create();
    $this->member = User::factory()->forOrganization($this->organization)->create();

    $this->reporter = new NullAnalyticsReporter();
    $this->app->instance(AnalyticsReporter::class, $this->reporter);
});

/**
 * @return array<string, mixed>
 */
function fixtureAttributes(Organization $organization, User $user): array
{
    return [
        'organization_id' => $organization->id,
        'user_id' => $user->id,
        'resource' => 'organization-members',
        'name' => 'A view',
        'query' => ['q' => 'ada'],
        'is_default' => false,
    ];
}

it('binds the null reporter by default, so a clone with blank keys sends nothing anywhere', function (): void {
    $this->app->forgetInstance(AnalyticsReporter::class);

    expect(resolve(AnalyticsReporter::class))->toBeInstanceOf(NullAnalyticsReporter::class);
});

it('binds the PostHog reporter once a key is configured', function (): void {
    config()->set('services.posthog.key', 'phc_test');
    $this->app->forgetInstance(AnalyticsReporter::class);

    expect(resolve(AnalyticsReporter::class))->toBeInstanceOf(PostHogReporter::class);
});

it('emits one created and one deleted event carrying ids only, never attribute values', function (): void {
    $search = resolve(OrganizationContext::class)->runAs(
        $this->organization,
        fn (): SavedSearch => SavedSearch::factory()->create([
            'organization_id' => $this->organization->id,
            'user_id' => $this->member->id,
        ]),
    );

    resolve(OrganizationContext::class)->runAs($this->organization, fn () => $search->delete());

    $events = collect($this->reporter->events);

    $created = $events->where('name', 'saved_search.created');
    $deleted = $events->where('name', 'saved_search.deleted');

    expect($created)->toHaveCount(1)
        ->and($deleted)->toHaveCount(1);

    foreach ([...$created, ...$deleted] as $event) {
        expect(array_keys($event['payload']))->toBe(['model', 'id', 'organization_id'])
            ->and($event['payload']['model'])->toBe('SavedSearch')
            ->and($event['payload']['id'])->toBe($search->id)
            ->and($event['payload']['organization_id'])->toBe($this->organization->id);
    }
});

it('stays silent on update unless the model opts in with #[Track]', function (): void {
    $search = resolve(OrganizationContext::class)->runAs(
        $this->organization,
        fn (): SavedSearch => SavedSearch::factory()->create([
            'organization_id' => $this->organization->id,
            'user_id' => $this->member->id,
        ]),
    );

    resolve(OrganizationContext::class)->runAs(
        $this->organization,
        fn () => $search->update(['name' => 'Renamed']),
    );

    expect(collect($this->reporter->events)->where('name', 'saved_search.updated'))->toHaveCount(0);
});

it('sends changed attribute names only — never a value — for a model opted in with #[Track]', function (): void {
    $fixture = AnalyticsTrackedFixture::create(fixtureAttributes($this->organization, $this->member));

    resolve(OrganizationContext::class)->runAs(
        $this->organization,
        fn () => $fixture->update(['name' => 'Secret new name']),
    );

    $event = collect($this->reporter->events)->where('name', 'analytics_tracked_fixture.updated')->sole();

    expect($event['payload']['changed'])->toBe(['name'])
        ->and(json_encode($event['payload']))->not->toContain('Secret new name');
});

it('emits nothing at all for a model marked #[NoTrack]', function (): void {
    $fixture = AnalyticsSilencedFixture::create(fixtureAttributes($this->organization, $this->member));

    resolve(OrganizationContext::class)->runAs($this->organization, function () use ($fixture): void {
        $fixture->update(['name' => 'Changed']);
        $fixture->delete();
    });

    expect(collect($this->reporter->events)->filter(
        fn (array $event): bool => str_starts_with($event['name'] ?? '', 'analytics_silenced_fixture.'),
    ))->toHaveCount(0);
});

it('opts out exactly the declared models — telemetry never tracks telemetry', function (): void {
    $declared = [Activity::class, ApiRequestLog::class];

    $optedOut = collect(glob(app_path('Models/*.php')))
        ->map(fn (string $file): string => 'App\\Models\\'.basename($file, '.php'))
        ->filter(fn (string $class): bool => is_subclass_of($class, Model::class)
            && in_array(BelongsToOrganization::class, class_uses_recursive($class), true))
        ->filter(fn (string $class): bool => new ReflectionClass($class)->getAttributes(NoTrack::class) !== [])
        ->values()
        ->all();

    expect($optedOut)->toEqualCanonicalizing($declared);
});

it('ignores models that do not belong to an organization', function (): void {
    User::factory()->create();

    expect(collect($this->reporter->events)->where('name', 'user.created'))->toHaveCount(0);
});

it('dispatches nothing server-side under DNT: 1, regardless of driver', function (): void {
    request()->headers->set('DNT', '1');

    resolve(OrganizationContext::class)->runAs(
        $this->organization,
        fn (): SavedSearch => SavedSearch::factory()->create([
            'organization_id' => $this->organization->id,
            'user_id' => $this->member->id,
        ]),
    );

    expect($this->reporter->events)->toBe([]);

    Queue::fake();
    new PostHogReporter()->track('anything', []);
    new PostHogReporter()->identify('u1');
    new PostHogReporter()->group('o1');

    Queue::assertNothingPushed();
});

it('dispatches nothing during a request that carries DNT: 1', function (): void {
    $this->actingAs($this->member)
        ->withHeader('DNT', '1')
        ->post(route('saved-search.store'), [
            'resource' => 'organization-members',
            'name' => 'Quiet view',
            'query' => ['q' => 'ada'],
        ])
        ->assertRedirect();

    expect($this->reporter->events)->toBe([]);
});

it('queues exactly one HTTP POST per PostHog event and never blocks the request', function (): void {
    Queue::fake();

    new PostHogReporter()->track('saved_search.created', [
        'model' => 'SavedSearch',
        'id' => 'abc',
        'organization_id' => 'org',
    ]);

    Queue::assertPushed(SendAnalyticsEvent::class, 1);
    Queue::assertPushed(SendAnalyticsEvent::class, fn (SendAnalyticsEvent $job): bool => $job->body['event'] === 'saved_search.created'
        && $job->body['distinct_id'] === 'anonymous'
        && $job->body['properties'] === ['model' => 'SavedSearch', 'id' => 'abc', 'organization_id' => 'org']);

    expect(new SendAnalyticsEvent([]))->toBeInstanceOf(ShouldQueue::class);
});

it('rides identity and groups on the same capture endpoint', function (): void {
    Queue::fake();

    new PostHogReporter()->identify('user-1', ['plan' => 'pro']);
    new PostHogReporter()->group('org-1', ['seats' => 3]);

    Queue::assertPushed(SendAnalyticsEvent::class, fn (SendAnalyticsEvent $job): bool => $job->body['event'] === '$identify'
        && $job->body['distinct_id'] === 'user-1'
        && $job->body['properties'] === ['$set' => ['plan' => 'pro']]);

    Queue::assertPushed(SendAnalyticsEvent::class, fn (SendAnalyticsEvent $job): bool => $job->body['event'] === '$groupidentify'
        && $job->body['properties']['$group_key'] === 'org-1');
});

it('records identify, group and reset on the null reporter', function (): void {
    $reporter = new NullAnalyticsReporter();

    $reporter->identify('user-1', ['plan' => 'pro']);
    $reporter->group('org-1', ['seats' => 3]);
    $reporter->reset();

    expect($reporter->events)->toBe([
        ['type' => 'identify', 'name' => 'user-1', 'payload' => ['plan' => 'pro']],
        ['type' => 'group', 'name' => 'org-1', 'payload' => ['seats' => 3]],
        ['type' => 'reset', 'name' => '', 'payload' => []],
    ]);
});

it('treats reset as a no-op on the posthog reporter', function (): void {
    Queue::fake();

    new PostHogReporter()->reset();

    Queue::assertNothingPushed();
});

it('posts the queued event to the configured host with the configured key', function (): void {
    config()->set('services.posthog.key', 'phc_test');
    config()->set('services.posthog.host', 'https://eu.posthog.com');

    Http::fake(['https://eu.posthog.com/*' => Http::response(['status' => 1])]);

    new SendAnalyticsEvent([
        'event' => 'saved_search.created',
        'distinct_id' => 'user-1',
        'properties' => ['model' => 'SavedSearch'],
    ])->handle();

    Http::assertSentCount(1);
    Http::assertSent(fn ($request): bool => $request->url() === 'https://eu.posthog.com/capture/'
        && $request['api_key'] === 'phc_test'
        && $request['event'] === 'saved_search.created'
        && $request['distinct_id'] === 'user-1');
});
