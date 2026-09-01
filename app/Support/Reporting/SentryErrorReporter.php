<?php

declare(strict_types=1);

namespace App\Support\Reporting;

use App\Contracts\ErrorReporter;
use Sentry\State\HubInterface;
use Sentry\State\Scope;
use Throwable;

/**
 * Forwards to the Sentry hub the sentry-laravel package binds. Only chosen
 * when a DSN is configured; a clone without one never constructs this class,
 * so the dev-only package is never required in production.
 */
final class SentryErrorReporter implements ErrorReporter
{
    use ReportsErrors;

    public function __construct(private readonly HubInterface $hub) {}

    /**
     * @param  array<string, mixed>  $context
     */
    protected function send(Throwable $throwable, string $reference, array $context): void
    {
        $this->hub->configureScope(function (Scope $scope) use ($reference, $context): void {
            $scope->setTag('reference', $reference);

            if (is_string($context['release'])) {
                $scope->setTag('release', $context['release']);
            }

            $scope->setContext('app', $context);
        });

        $this->hub->captureException($throwable);
    }
}
