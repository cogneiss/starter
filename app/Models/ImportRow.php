<?php

declare(strict_types=1);

namespace App\Models;

use Carbon\CarbonInterface;
use Database\Factories\ImportRowFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * One line of one imported file, with what became of it.
 *
 * The organization is not repeated here: a row is only ever reached through
 * {@see ImportBatch::rows()}, and the batch is organization-scoped, so the join
 * carries the boundary rather than a second column that could disagree with it.
 *
 * @property-read string $id
 * @property-read string $import_batch_id
 * @property int $line_number
 * @property array<string, string> $data
 * @property string $status
 * @property list<string>|null $errors
 * @property-read CarbonInterface $created_at
 * @property-read CarbonInterface $updated_at
 * @property-read ImportBatch $batch
 */
#[Fillable([
    'import_batch_id',
    'line_number',
    'data',
    'status',
    'errors',
])]
final class ImportRow extends Model
{
    /** @use HasFactory<ImportRowFactory> */
    use HasFactory;

    use HasUuids;

    public const string PENDING = 'pending';

    public const string IMPORTED = 'imported';

    public const string FAILED = 'failed';

    /**
     * @return array<string, string>
     */
    public function casts(): array
    {
        return [
            'id' => 'string',
            'import_batch_id' => 'string',
            'line_number' => 'integer',
            'data' => 'array',
            'errors' => 'array',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }
}
