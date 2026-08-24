<?php

declare(strict_types=1);

namespace App\Ai\Agents\Concerns;

use App\Ai\Middleware\EnforceQuota;
use App\Ai\Middleware\FenceUntrustedInput;
use App\Ai\Middleware\FilterTopics;
use App\Ai\Middleware\RecordAudit;

/**
 * The middleware pipeline every first-party agent runs, in the one order that
 * is correct: quota first, so a prompt that will be rejected is never paid for,
 * and audit last, so the record describes what actually went out rather than
 * what was asked for.
 */
trait HasDefaultMiddleware
{
    /**
     * @return list<class-string>
     */
    public function middleware(): array
    {
        return [
            EnforceQuota::class,
            FenceUntrustedInput::class,
            FilterTopics::class,
            RecordAudit::class,
        ];
    }
}
