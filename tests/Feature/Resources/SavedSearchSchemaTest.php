<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The shape a saved search is stored in.
 *
 * `organization_id` and `user_id` are the pair every read and write is
 * predicated on, so their absence would not be a missing feature — it would be
 * a table nobody can scope.
 */
it('stores a saved search with the columns its scope depends on', function (): void {
    expect(Schema::hasColumns('saved_searches', [
        'id',
        'organization_id',
        'user_id',
        'resource',
        'name',
        'query',
        'is_default',
        'created_at',
        'updated_at',
    ]))->toBeTrue();
});

it('keeps the query as json rather than a string', function (): void {
    // SQLite has one storage class for text and json and reports the column as
    // `text`, so the declaration is what has to be asserted there: it is what
    // every other engine turns into a real json column.
    if (DB::connection()->getDriverName() === 'sqlite') {
        $migration = array_values(array_filter(
            (array) glob(database_path('migrations/*_create_saved_searches_table.php')),
        ));

        expect(file_get_contents((string) $migration[0]))->toContain("json('query')");

        return;
    }

    expect(Schema::getColumnType('saved_searches', 'query'))->toContain('json');
});

/**
 * A foreign key can only be added after the table it points at exists, and
 * migrations run in filename order.
 */
it('runs after the tables it references', function (): void {
    $files = array_map(
        basename(...),
        (array) glob(database_path('migrations/*.php')),
    );

    $position = fn (string $needle): int => (int) array_key_first(
        array_filter($files, fn (string $file): bool => str_contains($file, $needle)),
    );

    expect($position('create_saved_searches_table'))
        ->toBeGreaterThan($position('create_organizations_table'))
        ->toBeGreaterThan($position('create_users_table'));
});
