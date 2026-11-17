<?php

declare(strict_types=1);

use App\Models\Organization;
use App\Models\OrganizationInvitation;
use App\Models\User;
use App\Resources\Definitions\OrganizationInvitationResource;
use App\Resources\Definitions\OrganizationMemberResource;
use App\Support\OrganizationContext;
use App\Support\ResourceQuery;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * A list request with the given query string, as the framework would hand it to
 * a controller.
 */
function listRequest(array $query = []): Request
{
    return Request::create('/settings/members', 'GET', $query);
}

it('falls back to the default order when the sort column is unknown', function (): void {
    $query = ResourceQuery::fromRequest(
        listRequest(['sort' => 'two_factor_secret']),
        new OrganizationMemberResource,
    );

    expect($query->sort)->toBe('user.name')
        ->and($query->dir)->toBe('asc');
});

it('keeps a sort column the resource allows, in the direction asked for', function (): void {
    $query = ResourceQuery::fromRequest(
        listRequest(['sort' => 'status', 'dir' => 'desc']),
        new OrganizationMemberResource,
    );

    expect($query->sort)->toBe('status')
        ->and($query->dir)->toBe('desc');
});

it('renders a list rather than an error when the sort column does not exist', function (): void {
    $organization = Organization::factory()->create();
    $owner = User::factory()->forOrganization($organization)->create();

    $this->actingAs($owner)
        ->get(route('organization-member.edit', ['sort' => 'two_factor_secret', 'dir' => 'sideways']))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('members.query.sort', 'user.name')
            ->where('members.query.dir', 'asc')
            ->has('members.rows', 1));
});

it('clamps the page size to an allowed one', function (array $asked, int $expected): void {
    $query = ResourceQuery::fromRequest(listRequest($asked), new OrganizationMemberResource);

    expect($query->per)->toBe($expected);
})->with([
    'far too many' => [['per' => '100000'], 100],
    'between two sizes' => [['per' => '30'], 25],
    'smaller than the smallest' => [['per' => '2'], 10],
    'not a number' => [['per' => 'all'], 10],
    'absent' => [[], 10],
]);

it('survives a page that is not a page', function (array $asked, int $expected): void {
    $query = ResourceQuery::fromRequest(listRequest($asked), new OrganizationMemberResource);

    expect($query->page)->toBe($expected);
})->with([
    'negative' => [['page' => '-4'], 1],
    'zero' => [['page' => '0'], 1],
    'a word' => [['page' => 'last'], 1],
    'an array' => [['page' => ['1']], 1],
    'a real page' => [['page' => '3'], 3],
]);

it('takes a term as text and nothing else', function (): void {
    $long = str_repeat('a', 300);

    expect(ResourceQuery::fromRequest(listRequest(['q' => "  {$long}  "]), new OrganizationMemberResource)->q)
        ->toBe(str_repeat('a', 255))
        ->and(ResourceQuery::fromRequest(listRequest(['q' => ['array']]), new OrganizationMemberResource)->q)
        ->toBe('');
});

it('names the relations a term reaches through, once each', function (): void {
    expect(ResourceQuery::relationsIn(['user.name', 'user.email', 'status']))->toBe(['user']);
});

it('orders through a relation without multiplying rows', function (): void {
    $organization = Organization::factory()->create();
    User::factory()->forOrganization($organization)->create(['name' => 'Zoe']);
    User::factory()->forOrganization($organization, 'Member')->create(['name' => 'Ada']);

    $resource = new OrganizationMemberResource;

    $names = resolve(OrganizationContext::class)->runAs($organization, function () use ($resource): array {
        $query = ResourceQuery::fromRequest(listRequest(['sort' => 'user.name']), $resource);

        return $query->applyTo($resource->scopedQuery(), $resource)
            ->get()
            ->map(fn ($membership): string => (string) $membership->user->name)
            ->all();
    });

    expect($names)->toBe(['Ada', 'Zoe']);
});

/**
 * The invitation emails a term finds in one organization, in address order.
 *
 * @param  list<string>  $emails
 * @return list<string>
 */
function searchInvitations(Organization $organization, array $emails, string $term): array
{
    foreach ($emails as $email) {
        OrganizationInvitation::factory()->create(['organization_id' => $organization->id, 'email' => $email]);
    }

    $resource = new OrganizationInvitationResource;

    return resolve(OrganizationContext::class)->runAs($organization, function () use ($resource, $term): array {
        $query = ResourceQuery::fromRequest(listRequest(['sort' => 'email', 'q' => $term]), $resource);

        return $query->applyTo($resource->scopedQuery(), $resource)->pluck('email')->all();
    });
}

it('orders by a plain column', function (): void {
    $organization = Organization::factory()->create();

    $found = searchInvitations($organization, ['b@example.com', 'a@example.com'], '');

    expect($found)->toBe(['a@example.com', 'b@example.com']);
});

it('matches the start of every word of the term, in any column order', function (string $term, array $expected): void {
    $organization = Organization::factory()->create();

    $emails = ['user@gmail.com', 'anne-smith@example.org', 'John@acme.test', 'José.Núñez@example.org', 'Müller@corp.test'];

    expect(searchInvitations($organization, $emails, $term))->toBe($expected);
})->with([
    'a part of an email address' => ['gmail', ['user@gmail.com']],
    'a part of a hyphenated value' => ['smith', ['anne-smith@example.org']],
    'a word prefix' => ['joh', ['John@acme.test']],
    'a mid-word substring is not a match' => ['ohn', []],
    'an accented name' => ['josé', ['José.Núñez@example.org']],
    'an accented name in capitals' => ['JOSÉ', ['José.Núñez@example.org']],
    'a later accented word' => ['núñ', ['José.Núñez@example.org']],
    'a umlaut' => ['Müller', ['Müller@corp.test']],
    'every word must match' => ['anne smith', ['anne-smith@example.org']],
    'words that never meet match nothing' => ['anne gmail', []],
    'words in any order' => ['acme john', ['John@acme.test']],
    'tsquery operators are only separators' => ['john:*|(', ['John@acme.test']],
    'quotes and statements are only text' => ["'; drop table x; --", []],
])->skip(fn (): bool => DB::getDriverName() !== 'pgsql', 'Full-text search is Postgres only.');

it('matches a term with no letters or digits as literal text on every engine', function (): void {
    $organization = Organization::factory()->create();

    $emails = ['a@example.com', 'b@example.com'];

    expect(searchInvitations($organization, $emails, '@@ --'))->toBe([])
        ->and(searchInvitations($organization, [], '@'))->toBe($emails)
        ->and(searchInvitations($organization, [], '%'))->toBe([]);
});

it('never returns a row of another organization that matches the term', function (): void {
    $organization = Organization::factory()->create();
    $other = Organization::factory()->create();

    OrganizationInvitation::factory()->create(['organization_id' => $other->id, 'email' => 'shared@elsewhere.test']);

    $found = searchInvitations($organization, ['shared@ours.test'], 'shared');

    expect($found)->toBe(['shared@ours.test']);
});

it('keeps the substring match on an engine without full-text search', function (): void {
    DB::connectUsing('lite', ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => ''], true);

    Schema::connection('lite')->create('organization_invitations', function (Blueprint $table): void {
        $table->id();
        $table->string('email');
        $table->string('role');
    });

    DB::connection('lite')->table('organization_invitations')->insert([
        ['email' => 'a_b@example.com', 'role' => 'Member'],
        ['email' => 'axb@example.com', 'role' => 'Member'],
        ['email' => 'John@example.com', 'role' => 'Member'],
    ]);

    $resource = new OrganizationInvitationResource;

    $search = fn (string $term): array => ResourceQuery::fromRequest(listRequest(['q' => $term]), $resource)
        ->applyTo(OrganizationInvitation::on('lite')->withoutGlobalScopes(), $resource)
        ->orderBy('email')
        ->pluck('email')
        ->all();

    // Mid-word, case-insensitive, and the underscore is the character itself.
    try {
        expect($search('ohn'))->toBe(['John@example.com'])
            ->and($search('a_b'))->toBe(['a_b@example.com'])
            ->and($search('@@'))->toBe([]);
    } finally {
        DB::purge('lite');
    }
});
