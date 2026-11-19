<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;

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
