<?php

declare(strict_types=1);

use App\Models\LoginHistory;
use App\Models\OrganizationInvitation;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;

/**
 * The two index rules in .ai/rules/database.md, checked against the PostgreSQL
 * catalog and not against the migration files. The query is read from the
 * database-design skill, so an agent and this suite run the same statement.
 */
it('leaves no foreign key or organization_id column without an index that leads with it', function (): void {
    $skill = (string) file_get_contents(base_path('.ai/skills/database-design/SKILL.md'));

    expect(preg_match_all('/^```sql\n(.*?)^```$/ms', $skill, $blocks))->toBe(1, 'The skill holds exactly one sql block.');

    $gaps = array_map(
        fn (stdClass $row): string => "{$row->gap}: {$row->table_name}.{$row->column_name}",
        DB::select($blocks[1][0]),
    );

    expect($gaps)->toBe([]);
})->skip(fn (): bool => DB::getDriverName() !== 'pgsql', 'The gap query reads the PostgreSQL catalog.');

/**
 * A timestamp column holds UTC only while the application and the database
 * session agree on the zone. config/app.php sets the first and
 * config/database.php pins the second.
 */
it('pins the application and the PostgreSQL, MySQL and MariaDB sessions to UTC', function (): void {
    expect(config('app.timezone'))->toBe('UTC')
        ->and(config('database.connections.pgsql.timezone'))->toBe('UTC')
        ->and(config('database.connections.mysql.timezone'))->toBe('+00:00')
        ->and(config('database.connections.mariadb.timezone'))->toBe('+00:00');
});

it('opens PostgreSQL sessions in UTC', function (): void {
    expect(DB::selectOne("select current_setting('TimeZone') as zone")->zone)->toBe('UTC');
})->skip(fn (): bool => DB::getDriverName() !== 'pgsql', 'The setting is read from a PostgreSQL session.');

it('keeps time zone column types out of the migrations', function (): void {
    $offenders = [];

    foreach (File::files(database_path('migrations')) as $migration) {
        if (preg_match('/->\w+Tz\(|(?i:timestamptz|with time zone)/', $migration->getContents()) === 1) {
            $offenders[] = $migration->getFilename();
        }
    }

    expect($offenders)->toBe([]);
});

/**
 * A soft-deleted row keeps its unique value, so a unique rule has to see it.
 *
 * Known limit: this reads one Rule::unique(...) chain at a time. It does not
 * see a rule written as a 'unique:' string, one built with new Unique(), one
 * built in one statement and changed in another, or a chain with a closure
 * that holds a semicolon. Parse the files with nikic/php-parser when the
 * application starts to use any of those.
 */
it('never lets a unique validation rule skip soft-deleted rows', function (): void {
    $offenders = [];

    foreach (File::allFiles(app_path()) as $file) {
        if (preg_match('/Rule::unique\((?:(?!Rule::|;).)*?->(?:withoutTrashed\(|whereNull\(\s*[\'"]deleted_at[\'"])/s', $file->getContents()) === 1) {
            $offenders[] = $file->getRelativePathname();
        }
    }

    expect($offenders)->toBe([]);
});

/**
 * The email rule in .ai/rules/database.md, on every model whose table has an
 * email column. Nothing is saved: the case reads what the model would write.
 */
it('stores the address in lower case on every model with an email column', function (): void {
    $checked = [];
    $offenders = [];

    foreach (File::glob(app_path('Models/*.php')) as $path) {
        $model = new ('App\\Models\\'.basename((string) $path, '.php'));

        if (! Schema::hasColumn($model->getTable(), 'email')) {
            continue;
        }

        $checked[] = $model::class;
        $model->setAttribute('email', 'Ada@Example.COM');

        if ($model->getAttributes()['email'] !== 'ada@example.com') {
            $offenders[] = $model::class;
        }
    }

    expect($offenders)->toBe([])
        ->and($checked)->not->toBe([]);
})->group('email-case');

/**
 * The check constraint behind the email rule in .ai/rules/database.md: a write
 * that goes past the model cannot store an ASCII capital.
 *
 * Known limit: the gate runs these cases on PostgreSQL. bin/check-migrations
 * proves that the MySQL statement runs, and nothing standing proves that MySQL
 * refuses a capital. A MySQL run of this file is the upgrade.
 */
it('refuses a capital in users email written past the model', function (): void {
    $user = User::factory()->create();

    expect(DB::table('users')->where('id', $user->id)->update(['email' => 'ada@example.com']))->toBe(1)
        ->and(fn (): int => DB::table('users')->where('id', $user->id)->update(['email' => 'Ada@example.com']))
        ->toThrow(QueryException::class, 'users_email_lowercase');
})->group('email-case')->skip(fn (): bool => DB::getDriverName() === 'sqlite', 'SQLite has no check constraint on the column.');

it('refuses a capital in password_reset_tokens email written past the model', function (): void {
    DB::table('password_reset_tokens')->insert(['email' => 'reset@example.com', 'token' => 'token']);

    expect(DB::table('password_reset_tokens')->where('email', 'reset@example.com')->update(['email' => 'ada@example.com']))->toBe(1)
        ->and(fn (): int => DB::table('password_reset_tokens')->where('email', 'ada@example.com')->update(['email' => 'Ada@example.com']))
        ->toThrow(QueryException::class, 'password_reset_tokens_email_lowercase');
})->group('email-case')->skip(fn (): bool => DB::getDriverName() === 'sqlite', 'SQLite has no check constraint on the column.');

it('refuses a capital in login_histories email written past the model', function (): void {
    $login = LoginHistory::factory()->create();

    expect(DB::table('login_histories')->where('id', $login->id)->update(['email' => 'ada@example.com']))->toBe(1)
        ->and(fn (): int => DB::table('login_histories')->where('id', $login->id)->update(['email' => 'Ada@example.com']))
        ->toThrow(QueryException::class, 'login_histories_email_lowercase');
})->group('email-case')->skip(fn (): bool => DB::getDriverName() === 'sqlite', 'SQLite has no check constraint on the column.');

it('refuses a capital in organization_invitations email written past the model', function (): void {
    $invitation = OrganizationInvitation::factory()->create();

    expect(DB::table('organization_invitations')->where('id', $invitation->id)->update(['email' => 'ada@example.com']))->toBe(1)
        ->and(fn (): int => DB::table('organization_invitations')->where('id', $invitation->id)->update(['email' => 'Ada@example.com']))
        ->toThrow(QueryException::class, 'organization_invitations_email_lowercase');
})->group('email-case')->skip(fn (): bool => DB::getDriverName() === 'sqlite', 'SQLite has no check constraint on the column.');

/**
 * Every table with an email column, read from the catalog, so a new one cannot
 * leave the constraint out.
 */
it('holds a lower-case check constraint on every table with an email column', function (): void {
    $tables = array_column(DB::select("select table_name from information_schema.columns where table_schema = 'public' and column_name = 'email' order by table_name"), 'table_name');

    $held = array_column(DB::select("select conrelid::regclass::text as table_name from pg_constraint where contype = 'c' and connamespace = 'public'::regnamespace and conname = conrelid::regclass::text || '_email_lowercase' and pg_get_constraintdef(oid) like '%email%!~ ''[A-Z]''%' order by 1"), 'table_name');

    expect($tables)->not->toBe([])
        ->and(array_values(array_diff($tables, $held)))->toBe([]);
})->group('email-case')->skip(fn (): bool => DB::getDriverName() !== 'pgsql', 'The constraints are read from the PostgreSQL catalog.');
