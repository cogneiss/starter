<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;

/**
 * The gating suite runs on PostgreSQL, because pgvector — and so vector search —
 * only exists there. A checkout that silently fell back to SQLite would still be
 * green everywhere else, which is exactly the failure this catches.
 */
it('runs the suite on postgresql', function (): void {
    expect(DB::connection()->getDriverName())->toBe('pgsql');
})->group('pgvector');
