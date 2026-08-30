<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Actions\PromoteTempUpload;
use App\Imports\ImportContract;
use App\Imports\ImportRegistry;
use App\Imports\ImportRunner;
use App\Models\ImportBatch;
use App\Models\ImportRow;
use App\Models\Organization;
use App\Models\TempUpload;
use App\Models\User;
use App\Support\OrganizationContext;
use Generator;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\LazyCollection;

/**
 * Reads one uploaded file and imports what is in it.
 *
 * The file is never read in a request and never held in memory: it is streamed
 * a line at a time into `import_rows` in chunks, and the rows are then executed
 * in chunks of their own, so a file of a hundred thousand lines costs the same
 * memory as a file of ten.
 *
 * A worker keeps its container between jobs, so the organization the last batch
 * bound is still bound when this one starts. Everything below runs inside an
 * explicit rebind — without it, a second tenant's rows land in the first
 * tenant's organization, which no test of a single batch would ever notice.
 */
final class ParseImportBatch implements ShouldQueue
{
    use Queueable;

    /** Rows written and executed per round trip. */
    private const int CHUNK = 200;

    /** Seconds to wait for the scanner before asking again. */
    private const int SCAN_RETRY_AFTER = 10;

    public function __construct(
        private readonly string $batchId,
        private readonly string $organizationId,
        private readonly string $userId,
        private readonly bool $onlyFailures = false,
    ) {}

    public function handle(ImportRegistry $imports, ImportRunner $runner): void
    {
        $organization = Organization::query()->findOrFail($this->organizationId);

        OrganizationContext::run($organization, function () use ($imports, $runner): void {
            $batch = ImportBatch::query()->findOrFail($this->batchId);
            $import = $imports->get($batch->import);
            $user = User::query()->findOrFail($this->userId);

            if ($this->onlyFailures) {
                $this->execute($batch, $import, $user, $runner, ImportRow::FAILED);

                return;
            }

            if (! $this->readable($batch)) {
                return;
            }

            $this->parse($batch, $import);
            $this->execute($batch, $import, $user, $runner, ImportRow::PENDING);

            $batch->forceFill(['status' => 'complete'])->save();
        });
    }

    /**
     * Whether the uploaded bytes may be opened at all.
     *
     * An upload nobody has scanned yet is not a failure, it is a job that
     * arrived before the scanner finished, so it goes back on the queue. An
     * upload the scanner refused ends the batch.
     */
    private function readable(ImportBatch $batch): bool
    {
        $upload = $batch->tempUpload;

        if (! $upload instanceof TempUpload) {
            return false;
        }

        if ($upload->scanned_at === null) {
            $this->release(self::SCAN_RETRY_AFTER);

            return false;
        }

        if (! resolve(PromoteTempUpload::class)->handle($upload)) {
            $batch->forceFill(['status' => 'rejected'])->save();

            return false;
        }

        return true;
    }

    /**
     * Stream the file into rows, a chunk at a time.
     */
    private function parse(ImportBatch $batch, ImportContract $import): void
    {
        $upload = $batch->tempUpload;
        assert($upload instanceof TempUpload);

        $stream = Storage::disk($upload->disk)->readStream($upload->path);
        assert(is_resource($stream));

        LazyCollection::make(fn (): Generator => yield from $this->lines($stream, $import))
            ->chunk(self::CHUNK)
            ->each(fn (LazyCollection $chunk): mixed => $batch->rows()->createMany($chunk->all()));
    }

    /**
     * @param  resource  $stream
     * @return Generator<int, array<string, mixed>>
     */
    private function lines(mixed $stream, ImportContract $import): Generator
    {
        $number = 0;

        try {
            while (($values = fgetcsv($stream, escape: '\\')) !== false) {
                $number++;

                // The first line is the heading row the template wrote.
                if ($number === 1) {
                    continue;
                }

                $data = [];

                foreach ($import->columns() as $index => $column) {
                    $data[$column] = mb_trim($values[$index] ?? '');
                }

                yield ['line_number' => $number, 'data' => $data, 'status' => ImportRow::PENDING];
            }
        } finally {
            fclose($stream);
        }
    }

    /**
     * Run every row in the batch that currently holds this status.
     */
    private function execute(
        ImportBatch $batch,
        ImportContract $import,
        User $user,
        ImportRunner $runner,
        string $status,
    ): void {
        $batch->rows()
            ->where('status', $status)
            ->chunkById(self::CHUNK, function (Collection $rows) use ($import, $user, $runner): void {
                foreach ($rows as $row) {
                    $runner->runRow($import, $user, $row);
                }
            });
    }
}
