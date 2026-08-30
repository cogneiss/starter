<?php

declare(strict_types=1);

namespace App\Imports;

use App\Models\User;

/**
 * What one importable resource is: the columns a file may carry, the rules each
 * line has to satisfy, who may import a given line, and what importing it does.
 *
 * The blank template offered for download is generated from {@see columns()},
 * so the file people fill in and the parser that reads it back cannot drift
 * apart — there is only one list.
 *
 * An implementation never writes a record itself. It calls the same Action the
 * screen calls, so an imported record and a typed one go through one path with
 * one set of validation, events and notifications.
 */
interface ImportContract
{
    /**
     * The importer's key, as it appears in a URL and in `import_batches.import`.
     */
    public function key(): string;

    /**
     * Column headings, in the order the template writes them.
     *
     * @return list<string>
     */
    public function columns(): array;

    /**
     * Validation rules for one row, keyed by column.
     *
     * @return array<string, mixed>
     */
    public function rules(): array;

    /**
     * Whether this person may import this particular row.
     *
     * The answer depends on the row, not only on the importer: a file may name
     * two different targets the caller has two different answers for.
     *
     * @param  array<string, string>  $row
     */
    public function authorizeRow(User $user, array $row): bool;

    /**
     * Import one validated, authorized row.
     *
     * @param  array<string, string>  $row
     */
    public function handle(User $user, array $row): void;
}
