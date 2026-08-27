<?php

declare(strict_types=1);

namespace Tests\Fixtures\Resources\Filters;

use Spatie\LaravelData\Data;

/**
 * The little a filter test needs to see of a row: which record it is, and one
 * numeric column a range filter can be checked against.
 */
final class AuditLogRowData extends Data
{
    public function __construct(
        public string $id,
        public int $total_tokens,
    ) {}
}
