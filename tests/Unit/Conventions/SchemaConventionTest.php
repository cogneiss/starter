<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;

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
