<?php

declare(strict_types=1);

use App\Actions\DeleteAccount;
use App\Exceptions\LastActiveOwnerException;
use App\Models\Activity;
use App\Models\ApiToken;
use App\Models\ImpersonationLog;
use App\Models\LoginHistory;
use App\Models\Organization;
use App\Models\OrganizationInvitation;
use App\Models\TempUpload;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

beforeEach(function (): void {
    Storage::fake('temp-uploads');
});

/**
 * Count schema-wide occurrences of a literal needle: every varchar, text and
 * json column of every table in the public schema, not a hand-picked subset.
 *
 * @return array<string, int>
 */
function sweepSchemaFor(string $needle): array
{
    $columns = DB::select(
        "select table_name, column_name from information_schema.columns
         where table_schema = 'public'
         and data_type in ('character varying', 'text', 'json', 'jsonb')",
    );

    $hits = [];

    foreach ($columns as $column) {
        $count = DB::selectOne(sprintf(
            'select count(*) as hits from %s where %s::text ilike ?',
            '"'.$column->table_name.'"',
            '"'.$column->column_name.'"',
        ), ['%'.$needle.'%'])->hits;

        if ($count > 0) {
            $hits[$column->table_name.'.'.$column->column_name] = (int) $count;
        }
    }

    return $hits;
}

it('erases every trace of the person from every table in the schema', function (): void {
    $organization = Organization::factory()->create();

    // Two owners, so the one being erased is not the last active owner.
    User::factory()->forOrganization($organization)->create();

    $user = User::factory()->forOrganization($organization)->create([
        'name' => 'Erasure Target Person',
        'email' => 'erasure.target@example.com',
    ]);

    LoginHistory::factory()->create([
        'user_id' => $user->id,
        'email' => 'erasure.target@example.com',
        'ip_address' => '203.0.113.77',
    ]);

    // A failed attempt has no user id — only the typed email ties it back.
    LoginHistory::factory()->create([
        'user_id' => null,
        'email' => 'erasure.target@example.com',
        'ip_address' => '203.0.113.77',
    ]);

    ImpersonationLog::factory()->create([
        'impersonated_user_id' => $user->id,
        'organization_id' => $organization->id,
        'ip_address' => '203.0.113.77',
    ]);

    OrganizationInvitation::factory()->create([
        'organization_id' => $organization->id,
        'email' => 'erasure.target@example.com',
        'invited_by_user_id' => $user->id,
    ]);

    $token = ApiToken::factory()->create([
        'organization_id' => $organization->id,
        'tokenable_id' => $user->id,
        'created_by' => $user->id,
    ]);

    Storage::disk('temp-uploads')->put('imports/pending.csv', 'email,role');
    TempUpload::factory()->create([
        'user_id' => $user->id,
        'organization_id' => $organization->id,
        'path' => 'imports/pending.csv',
    ]);

    DB::table('notifications')->insert([
        'id' => (string) Str::uuid(),
        'type' => 'App\Notifications\Example',
        'notifiable_type' => $user->getMorphClass(),
        'notifiable_id' => $user->id,
        'data' => json_encode(['title' => 'Hello Erasure Target Person']),
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    // An audit entry authored by the user: the row must survive, authorless.
    Activity::query()->create([
        'organization_id' => $organization->id,
        'log_name' => 'audit',
        'description' => 'Updated a record.',
        'event' => 'updated',
        'causer_type' => $user->getMorphClass(),
        'causer_id' => $user->id,
        'properties' => [],
    ]);

    $auditRowsBefore = DB::table('activity_log')->count();

    expect(DB::table('activity_log')->where('causer_id', $user->id)->count())->toBeGreaterThan(0);

    resolve(DeleteAccount::class)->handle($user);

    // The schema-wide sweep: the original email, name and IP survive in no
    // varchar, text or json column of any table.
    expect(sweepSchemaFor('erasure.target@example.com'))->toBe([])
        ->and(sweepSchemaFor('Erasure Target Person'))->toBe([])
        ->and(sweepSchemaFor('203.0.113.77'))->toBe([]);

    $user->refresh();

    expect($user->trashed())->toBeTrue()
        ->and($user->email)->toBe('anonymised-'.$user->id.'@example.invalid')
        ->and($user->name)->toBe('Anonymised user')
        ->and($user->remember_token)->toBeNull()
        ->and($user->two_factor_secret)->toBeNull()
        ->and($user->two_factor_recovery_codes)->toBeNull();

    // Tokens revoked, notifications removed, audit rows kept but authorless.
    expect($token->refresh()->revoked_at)->not->toBeNull()
        ->and(DB::table('notifications')->where('notifiable_id', $user->id)->count())->toBe(0)
        ->and(DB::table('activity_log')->where('causer_id', $user->id)->count())->toBe(0)
        ->and(DB::table('activity_log')->count())->toBeGreaterThanOrEqual($auditRowsBefore);

    // Pending upload bytes and row are both gone.
    Storage::disk('temp-uploads')->assertMissing('imports/pending.csv');
    expect(DB::table('temp_uploads')->where('user_id', $user->id)->count())->toBe(0);

    // Login history rows survive, anonymised in place.
    expect(LoginHistory::query()->where('email', 'anonymised@example.invalid')->count())->toBe(2);
});

it('audits the anonymisation distinctly from an export', function (): void {
    $organization = Organization::factory()->create();
    User::factory()->forOrganization($organization)->create();
    $user = User::factory()->forOrganization($organization)->create();

    resolve(DeleteAccount::class)->handle($user);

    $entry = Activity::withoutOrganizationScope()
        ->where('organization_id', $organization->id)
        ->where('event', 'anonymised')
        ->sole();

    expect($entry->event)->not->toBe('exported')
        ->and($entry->causer_id)->toBeNull();
});

it('gives two anonymised users distinct placeholders without a constraint clash', function (): void {
    $organization = Organization::factory()->create();
    User::factory()->forOrganization($organization)->create();
    $first = User::factory()->forOrganization($organization)->create();
    $second = User::factory()->forOrganization($organization)->create();

    $action = resolve(DeleteAccount::class);
    $action->handle($first);
    $action->handle($second);

    $first->refresh();
    $second->refresh();

    expect($first->email)->not->toBe($second->email)
        ->and($first->trashed())->toBeTrue()
        ->and($second->trashed())->toBeTrue();
});

it('refuses to erase the last active owner and changes nothing', function (): void {
    $organization = Organization::factory()->create();
    $owner = User::factory()->forOrganization($organization)->create();

    $membershipsBefore = DB::table('organization_memberships')->count();
    $email = $owner->email;

    expect(fn () => resolve(DeleteAccount::class)->handle($owner))
        ->toThrow(LastActiveOwnerException::class);

    expect(DB::table('organization_memberships')->count())->toBe($membershipsBefore)
        ->and($owner->refresh()->trashed())->toBeFalse()
        ->and($owner->email)->toBe($email);
});
