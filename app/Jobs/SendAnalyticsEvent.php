<?php

declare(strict_types=1);

namespace App\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Http;

/**
 * The one HTTP POST analytics ever makes, off the request path so a slow or
 * down provider never blocks a response.
 */
final class SendAnalyticsEvent implements ShouldQueue
{
    use Queueable;

    /**
     * @param  array<string, mixed>  $body
     */
    public function __construct(public array $body) {}

    public function handle(): void
    {
        Http::asJson()
            ->post(config()->string('services.posthog.host').'/capture/', [
                'api_key' => config()->string('services.posthog.key'),
                ...$this->body,
            ])
            ->throw();
    }
}
