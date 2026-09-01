<?php

declare(strict_types=1);

namespace App\Contracts;

use Throwable;

/**
 * Where a server error goes. Every report yields a reference id, and the API's
 * 500 payload carries the same id, so a person quoting an error can be matched
 * to the report. Context is identifiers only — organization id, user id,
 * request id, release — never a request body, a header or a token.
 */
interface ErrorReporter
{
    /**
     * Report the failure and return its reference id.
     */
    public function report(Throwable $throwable): string;

    /**
     * The reference id a failure was already reported under, if it was.
     */
    public function reference(Throwable $throwable): ?string;
}
