<?php

declare(strict_types=1);

namespace App\Support\Reporting;

use App\Contracts\ErrorReporter;
use Throwable;

/**
 * The default when no DSN is configured: reference ids are still minted so
 * the 500 payload works, but nothing leaves the process. What would have
 * been sent is kept in memory, which is what the tests assert against.
 */
final class NullErrorReporter implements ErrorReporter
{
    use ReportsErrors;

    /**
     * @var list<array{throwable: Throwable, reference: string, context: array<string, mixed>}>
     */
    public array $reports = [];

    /**
     * @param  array<string, mixed>  $context
     */
    protected function send(Throwable $throwable, string $reference, array $context): void
    {
        $this->reports[] = ['throwable' => $throwable, 'reference' => $reference, 'context' => $context];
    }
}
