<?php

declare(strict_types=1);

namespace App\Imports;

use App\Models\ImportRow;
use App\Models\User;
use Illuminate\Support\Facades\Validator;
use Throwable;

/**
 * Turns one stored line into one imported record, or into a recorded reason it
 * was not.
 *
 * Nothing here rethrows. A file of a hundred lines with three bad ones is three
 * corrections and ninety-seven records, not a hundred lines to type again, so
 * every way a single row can fail — invalid, not allowed, or an Action that
 * threw halfway through — ends as a message on that row.
 */
final readonly class ImportRunner
{
    public function runRow(ImportContract $import, User $user, ImportRow $row): void
    {
        $validator = Validator::make($row->data, $import->rules());

        if ($validator->fails()) {
            $this->fail($row, array_values($validator->errors()->all()));

            return;
        }

        if (! $import->authorizeRow($user, $row->data)) {
            $this->fail($row, [__('You are not allowed to import that row.')]);

            return;
        }

        try {
            $import->handle($user, $row->data);
        } catch (Throwable $throwable) {
            $this->fail($row, [$throwable->getMessage()]);

            return;
        }

        $row->forceFill(['status' => ImportRow::IMPORTED, 'errors' => null])->save();
    }

    /**
     * @param  list<string>  $errors
     */
    private function fail(ImportRow $row, array $errors): void
    {
        $row->forceFill(['status' => ImportRow::FAILED, 'errors' => $errors])->save();
    }
}
